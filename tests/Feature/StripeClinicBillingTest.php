<?php

namespace Tests\Feature;

use App\Models\ClinicWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StripeClinicBillingTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('plans')]
    public function test_signed_stripe_confirmation_controls_the_trial_and_paid_access(string $plan, int $amount): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.secret' => 'sk_test_example',
            'stripe.webhook_secret' => 'whsec_example',
        ]);

        $owner = User::factory()->create();
        $clinic = ClinicWorkspace::createForOwner($owner, 'Consultorio Stripe', trialRequired: true);
        $price = [
            'id' => config("stripe.prices.{$plan}"),
            'currency' => 'mxn',
            'unit_amount' => $amount,
            'livemode' => false,
            'recurring' => ['interval' => 'month', 'interval_count' => 1],
        ];
        $subscription = [
            'id' => 'sub_Example',
            'customer' => 'cus_Example',
            'status' => 'trialing',
            'livemode' => false,
            'trial_end' => now()->addDays(3)->timestamp,
            'items' => ['data' => [[
                'price' => $price,
                'quantity' => 1,
                'current_period_end' => now()->addMonth()->timestamp,
            ]]],
        ];
        $setup = [
            'mode' => 'setup',
            'status' => 'complete',
            'livemode' => false,
            'customer' => 'cus_Example',
            'metadata' => ['plan' => $plan],
            'setup_intent' => [
                'status' => 'succeeded',
                'payment_method' => [
                    'id' => 'pm_Example',
                    'customer' => 'cus_Example',
                    'card' => ['funding' => 'credit'],
                ],
            ],
        ];
        $debitSetup = $setup;
        $debitSetup['setup_intent']['payment_method']['card']['funding'] = 'debit';
        Http::preventStrayRequests();
        Http::fake([
            'api.stripe.com/v1/prices/*' => Http::response($price),
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_Example']),
            'api.stripe.com/v1/checkout/sessions' => Http::sequence()
                ->push(['id' => 'cs_test_Credit', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_Credit'])
                ->push(['id' => 'cs_test_Debit', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_Debit']),
            'api.stripe.com/v1/checkout/sessions/cs_test_Credit*' => Http::response($setup),
            'api.stripe.com/v1/checkout/sessions/cs_test_Debit*' => Http::response($debitSetup),
            'api.stripe.com/v1/subscriptions' => Http::response($subscription),
            'api.stripe.com/v1/subscriptions/*' => Http::sequence()
                ->push(array_replace($subscription, ['status' => 'active', 'latest_invoice' => ['status' => 'paid', 'amount_paid' => $amount]]))
                ->push(array_replace($subscription, ['status' => 'past_due'])),
        ]);

        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $clinic->id]);
        $this->post(route('clinic.plan.start'), ['plan' => $plan])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_Credit');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['mode'] === 'setup'
            && $request['payment_method_types'] === ['card']);
        $this->assertFalse($clinic->fresh()->hasWorkspaceAccess());

        $checkout = ['id' => 'cs_test_Credit', 'mode' => 'setup', 'status' => 'complete'];
        $this->event('checkout.session.completed', $checkout, valid: false)->assertBadRequest();
        $this->get(route('inicio'))->assertRedirect(route('clinic.plan'));
        $this->event('checkout.session.completed', $checkout)->assertNoContent();
        $this->assertNull($clinic->fresh()->stripe_subscription_id);
        $this->assertNull($clinic->fresh()->trial_ends_at);
        $this->get(route('clinic.plan'))->assertOk()->assertSee('Stripe no pudo confirmar');
        Http::assertNotSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/subscriptions');

        $this->post(route('clinic.plan.start'), ['plan' => $plan])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_Debit');
        $checkout['id'] = 'cs_test_Debit';
        $this->event('checkout.session.completed', $checkout)->assertNoContent();
        $this->event('checkout.session.completed', $checkout)->assertNoContent();
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/subscriptions'
            && $request['trial_period_days'] === 3
            && $request['default_payment_method'] === 'pm_Example'
            && $request['items'][0]['price'] === $price['id']);
        $this->assertTrue($clinic->fresh()->hasWorkspaceAccess());

        $this->event('customer.subscription.updated', ['id' => 'sub_Example'])->assertNoContent();
        $this->assertSame('active', $clinic->fresh()->stripe_status);
        $this->assertTrue($clinic->fresh()->hasWorkspaceAccess());
        $this->event('invoice.payment_failed', ['parent' => ['subscription_details' => ['subscription' => 'sub_Example']]])->assertNoContent();
        $this->get(route('reportes.export'))->assertRedirect(route('clinic.plan'));
    }

    public static function plans(): array
    {
        return [
            'Básico' => ['basico', 19900],
            'Consultorio' => ['consultorio', 49900],
        ];
    }

    private function event(string $type, array $object, bool $valid = true)
    {
        $body = json_encode(['id' => 'evt_Test', 'type' => $type, 'livemode' => false, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
        $time = time();
        $signature = hash_hmac('sha256', "{$time}.{$body}", $valid ? 'whsec_example' : 'incorrect');

        return $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }
}

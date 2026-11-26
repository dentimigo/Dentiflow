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

    public function test_owner_can_change_a_trial_plan_without_card_or_extending_the_trial(): void
    {
        config(['stripe.enabled' => true]);
        Http::fake();

        $owner = User::factory()->create();
        $clinic = ClinicWorkspace::createForOwner($owner, 'Consultorio de prueba', trialRequired: true);
        $trialEnd = now()->addDay()->startOfSecond();
        $clinic->forceFill(['trial_plan' => 'basico', 'trial_ends_at' => $trialEnd])->save();
        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $clinic->id, 'access_role' => 'admin']);

        $this->get(route('perfil'))->assertOk()->assertSee('Cambiar plan');
        $this->post(route('perfil.subscription.change'), ['plan' => 'consultorio', 'confirm' => '1'])
            ->assertRedirect(route('perfil'))->assertSessionHas('subscription_status');

        $clinic->refresh();
        $this->assertSame('consultorio', $clinic->trial_plan);
        $this->assertEquals($trialEnd->timestamp, $clinic->trial_ends_at->timestamp);
        Http::assertNothingSent();
    }

    public function test_only_the_owner_can_change_plan_and_the_team_must_fit(): void
    {
        config([
            'stripe.enabled' => true,
            'stripe.secret' => 'sk_test_example',
            'stripe.webhook_secret' => 'whsec_example',
        ]);

        $owner = User::factory()->create();
        $assistant = User::factory()->create();
        $clinic = ClinicWorkspace::createForOwner($owner, 'Consultorio Stripe', trialRequired: true);
        $trialEnd = now()->addDays(3)->startOfSecond();
        $clinic->forceFill([
            'trial_plan' => 'consultorio',
            'trial_ends_at' => $trialEnd,
            'stripe_customer_id' => 'cus_Example',
            'stripe_subscription_id' => 'sub_Example',
            'stripe_status' => 'trialing',
        ])->save();
        $clinic->members()->attach($assistant, ['role' => 'asistente']);
        $extra = User::factory()->create();
        $clinic->members()->attach($extra, ['role' => 'doctor']);
        $session = ['clinic_workspace_id' => $clinic->id, 'access_role' => 'admin'];
        $input = ['plan' => 'basico', 'confirm' => '1'];
        Http::preventStrayRequests();

        $this->actingAs($assistant)->withSession(array_replace($session, ['access_role' => 'asistente']))
            ->get(route('perfil'))->assertOk()->assertDontSee('id="subscription-heading"', false);
        $this->post(route('perfil.subscription.change'), $input)->assertForbidden();
        $this->actingAs($owner)->withSession($session)->get(route('perfil'))
            ->assertOk()->assertSee('Suscripción')->assertSee('499 MXN al mes');
        $this->post(route('perfil.subscription.change'), $input)
            ->assertSessionHasErrorsIn('subscription', 'plan');

        $clinic->members()->detach($extra);
        $price = [
            'currency' => 'mxn', 'livemode' => false, 'active' => true,
            'recurring' => ['interval' => 'month', 'interval_count' => 1],
        ];
        $basic = $price + ['id' => config('stripe.prices.basico'), 'unit_amount' => 19900];
        $subscription = [
            'id' => 'sub_Example', 'customer' => 'cus_Example', 'status' => 'trialing',
            'livemode' => false, 'trial_end' => $trialEnd->timestamp,
            'items' => ['data' => [[
                'id' => 'si_Example', 'quantity' => 1,
                'price' => $price + ['id' => config('stripe.prices.consultorio'), 'unit_amount' => 49900],
            ]]],
        ];
        $updated = $subscription;
        $updated['items']['data'][0]['price'] = $basic;
        Http::fake([
            'https://api.stripe.com/v1/prices/*' => Http::response($basic),
            'https://api.stripe.com/v1/subscriptions/sub_Example*' => fn (Request $request) =>
                Http::response($request->method() === 'POST' ? $updated : $subscription),
        ]);

        $this->post(route('perfil.subscription.change'), $input)
            ->assertRedirect(route('perfil'))->assertSessionHas('subscription_status');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['proration_behavior'] === 'none'
            && $request['billing_cycle_anchor'] === 'unchanged');
        $this->assertSame('basico', $clinic->fresh()->trial_plan);
        $this->assertEquals($trialEnd->timestamp, $clinic->fresh()->trial_ends_at->timestamp);
    }

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
                ->push(['id' => 'cs_test_Debit', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_Debit'])
                ->push(['id' => 'cs_test_Renewal', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_Renewal']),
            'api.stripe.com/v1/checkout/sessions/cs_test_Credit*' => Http::response($setup),
            'api.stripe.com/v1/checkout/sessions/cs_test_Debit*' => Http::sequence()
                ->pushStatus(503)
                ->push($debitSetup),
            'api.stripe.com/v1/subscriptions' => Http::response($subscription),
            'api.stripe.com/v1/billing_portal/sessions' => Http::sequence()
                ->pushStatus(400)
                ->push(['url' => 'https://billing.stripe.com/p/session/test']),
            'api.stripe.com/v1/subscriptions/*' => Http::sequence()
                ->push($subscription)
                ->push($subscription + ['cancel_at' => $subscription['trial_end']])
                ->push(array_replace($subscription, ['status' => 'active', 'latest_invoice' => ['status' => 'paid', 'amount_paid' => $amount]]))
                ->push(array_replace($subscription, ['status' => 'active', 'latest_invoice' => ['status' => 'paid', 'amount_paid' => $amount]]))
                ->push(array_replace($subscription, [
                    'status' => 'active',
                    'cancel_at_period_end' => true,
                    'latest_invoice' => ['status' => 'draft', 'amount_paid' => 0],
                ]))
                ->push(array_replace($subscription, ['status' => 'past_due']))
                ->push(array_replace($subscription, ['status' => 'canceled']))
                ->push(array_replace($subscription, [
                    'id' => 'sub_Renewal',
                    'status' => 'active',
                    'trial_end' => null,
                    'latest_invoice' => ['status' => 'paid', 'amount_paid' => $amount],
                ])),
        ]);

        $this->actingAs($owner)->withSession(['clinic_workspace_id' => $clinic->id]);
        $this->get(route('clinic.plan'))->assertOk()
            ->assertSee('type="submit" name="plan" value="basico"', false)
            ->assertSee('type="submit" name="plan" value="consultorio"', false);
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
        $this->assertNull($clinic->fresh()->stripe_status);
        $this->get(route('clinic.plan'))
            ->assertOk()->assertSee('Stripe HTTP 503')->assertSee('Reintentar confirmación')
            ->assertDontSee('Registrar débito e iniciar prueba');
        $this->assertSame('cs_test_Debit', $clinic->fresh()->stripe_checkout_id);
        $this->get(route('clinic.plan', ['stripe' => 'regreso']))
            ->assertOk()->assertSee('Tu prueba está activa')->assertDontSee('Stripe no pudo confirmar');
        $checkout['id'] = 'cs_test_Debit';
        $this->event('checkout.session.completed', $checkout)->assertNoContent();
        $this->event('checkout.session.completed', $checkout)->assertNoContent();
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/subscriptions'
            && $request['trial_period_days'] === 3
            && $request['default_payment_method'] === 'pm_Example'
            && $request['items'][0]['price'] === $price['id']);
        $this->assertTrue($clinic->fresh()->hasWorkspaceAccess());

        $this->post(route('clinic.plan.portal'))->assertRedirect(route('perfil'))
            ->assertSessionHasErrorsIn('subscription', 'portal');
        $this->post(route('clinic.plan.portal'))
            ->assertRedirect('https://billing.stripe.com/p/session/test');

        $this->post(route('perfil.subscription.cancel'), ['confirm_cancel' => '1'])
            ->assertRedirect(route('perfil'))->assertSessionHas('subscription_status');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.stripe.com/v1/subscriptions/sub_Example'
            && $request['cancel_at'] === $subscription['trial_end']);
        $this->assertTrue($clinic->fresh()->stripe_cancellation_pending);
        $this->assertTrue($clinic->fresh()->hasWorkspaceAccess());

        $this->event('customer.subscription.updated', ['id' => 'sub_Example'])->assertNoContent();
        $this->assertSame('active', $clinic->fresh()->stripe_status);
        $this->assertTrue($clinic->fresh()->hasWorkspaceAccess());

        $this->post(route('perfil.subscription.cancel'), ['confirm_cancel' => '1'])
            ->assertRedirect(route('perfil'))->assertSessionHas('subscription_status');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.stripe.com/v1/subscriptions/sub_Example'
            && $request['cancel_at_period_end'] === 'true');
        $this->assertTrue($clinic->fresh()->stripe_cancellation_pending);
        $this->get(route('pacientes'))->assertOk();

        $this->event('invoice.payment_failed', ['parent' => ['subscription_details' => ['subscription' => 'sub_Example']]])->assertNoContent();
        $this->get(route('reportes.export'))->assertRedirect(route('clinic.plan'));
        $this->get(route('citas.create'))->assertRedirect(route('clinic.plan'));
        $this->postJson(route('citas.store'))->assertForbidden()
            ->assertJsonPath('renewal_url', route('clinic.plan'));
        $this->get(route('clinic.plan'))->assertOk()
            ->assertSee('Renueva tu plan')->assertSee('Actualizar tarjeta de débito o crédito');

        $this->event('customer.subscription.deleted', ['id' => 'sub_Example'])->assertNoContent();
        $this->post(route('citas.store'))->assertRedirect(route('clinic.plan'));
        $this->get(route('clinic.plan'))->assertOk()
            ->assertSee('Renovar plan en Stripe')->assertDontSee('Actualizar tarjeta de débito');
        $this->post(route('clinic.plan.start'), ['plan' => $plan])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_Renewal');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['mode'] === 'subscription'
            && $request['payment_method_collection'] === 'always');
        $this->post(route('citas.store'))->assertRedirect(route('clinic.plan'));

        $this->event('checkout.session.completed', [
            'id' => 'cs_test_Renewal', 'mode' => 'subscription', 'status' => 'complete', 'subscription' => 'sub_Renewal',
        ])->assertNoContent();
        $this->assertTrue($clinic->fresh()->hasWorkspaceAccess());
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

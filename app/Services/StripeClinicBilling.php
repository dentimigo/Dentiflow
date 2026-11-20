<?php

namespace App\Services;

use App\Models\ClinicWorkspace;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StripeClinicBilling
{
    public function checkout(ClinicWorkspace $workspace, string $plan, string $ownerEmail): string
    {
        return DB::transaction(function () use ($workspace, $plan, $ownerEmail) {
            $workspace = ClinicWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $resubscribing = $workspace->stripe_status === 'canceled';
            if ($workspace->stripe_subscription_id && ! $resubscribing) {
                throw ValidationException::withMessages(['plan' => 'Gestiona tu suscripción desde el portal de Stripe.']);
            }

            $setupOnly = $workspace->trial_ends_at === null && ! $resubscribing;
            if ($resubscribing && $workspace->stripe_subscription_id) {
                $workspace->stripe_checkout_attempt++;
                $workspace->stripe_subscription_id = null;
            } elseif ($workspace->stripe_checkout_id) {
                $session = $this->api()->get('checkout/sessions/'.$workspace->stripe_checkout_id)->throw()->json();
                if ($session['status'] === 'open') {
                    if (data_get($session, 'metadata.plan') === $plan && ($session['mode'] === 'setup') === $setupOnly) {
                        return $this->stripeUrl($session['url'], 'checkout.stripe.com');
                    }

                    $this->api()->post('checkout/sessions/'.$workspace->stripe_checkout_id.'/expire')->throw();
                } elseif ($session['status'] !== 'expired') {
                    throw ValidationException::withMessages(['plan' => 'Estamos esperando la confirmación de Stripe.']);
                }

                $workspace->stripe_checkout_attempt++;
            }

            $price = config("stripe.prices.{$plan}");
            abort_unless(is_string($price) && preg_match('/^price_[A-Za-z0-9]+$/D', $price), 503);
            if ($workspace->members()->count() > ClinicWorkspace::TRIAL_PLANS[$plan]['seats']) {
                throw ValidationException::withMessages(['plan' => 'Elige un plan con espacio para todos los integrantes actuales.']);
            }

            $stripePrice = $this->api()->get('prices/'.$price)->throw()->json();
            abort_unless($this->validPrice($stripePrice, $plan), 503, 'El precio de Stripe no coincide con el plan.');

            if ($setupOnly && ! $workspace->stripe_customer_id) {
                $customer = $this->api()->withHeader('Idempotency-Key', 'dentiflow-customer-'.$workspace->id)
                    ->post('customers', ['email' => $ownerEmail])->throw()->json();
                $workspace->stripe_customer_id = $customer['id'];
            }

            $session = $this->api()
                ->withHeader('Idempotency-Key', 'dentiflow-checkout-'.$workspace->id.'-'.$workspace->stripe_checkout_attempt)
                ->post('checkout/sessions', [
                    'mode' => $setupOnly ? 'setup' : 'subscription',
                    'payment_method_types' => ['card'],
                    ...($workspace->stripe_customer_id
                        ? ['customer' => $workspace->stripe_customer_id]
                        : ['customer_email' => $ownerEmail]),
                    ...($setupOnly ? ['currency' => 'mxn'] : [
                        'payment_method_collection' => 'always',
                        'line_items' => [['price' => $price, 'quantity' => 1]],
                    ]),
                    'client_reference_id' => (string) $workspace->id,
                    'metadata' => ['plan' => $plan],
                    'success_url' => route('clinic.plan', ['stripe' => 'regreso']),
                    'cancel_url' => route('clinic.plan'),
                ])->throw()->json();

            $workspace->stripe_checkout_id = $session['id'];
            $workspace->save();

            return $this->stripeUrl($session['url'], 'checkout.stripe.com');
        });
    }

    public function completeDebitSetup(string $checkoutId): void
    {
        DB::transaction(function () use ($checkoutId) {
            $workspace = ClinicWorkspace::query()->where('stripe_checkout_id', $checkoutId)->lockForUpdate()->first();
            if (! $workspace || $workspace->stripe_subscription_id || $workspace->trial_ends_at !== null) {
                return;
            }

            $session = $this->api()->get('checkout/sessions/'.$checkoutId, [
                'expand' => ['setup_intent.payment_method'],
            ])->throw()->json();
            $plan = data_get($session, 'metadata.plan');
            if (($session['mode'] ?? null) !== 'setup' || ($session['status'] ?? null) !== 'complete'
                || ($session['livemode'] ?? true) || ($session['customer'] ?? null) !== $workspace->stripe_customer_id
                || data_get($session, 'setup_intent.status') !== 'succeeded' || ! is_string($plan)
                || ! isset(ClinicWorkspace::TRIAL_PLANS[$plan])) {
                return;
            }

            $card = data_get($session, 'setup_intent.payment_method');
            if (data_get($card, 'card.funding') !== 'debit' || data_get($card, 'customer') !== $workspace->stripe_customer_id
                || ! is_string(data_get($card, 'id'))) {
                $workspace->stripe_status = 'debit_required';
                $workspace->stripe_checkout_id = null;
                $workspace->stripe_checkout_attempt++;
                $workspace->save();

                return;
            }

            $priceId = config("stripe.prices.{$plan}");
            $price = $this->api()->get('prices/'.$priceId)->throw()->json();
            abort_unless($this->validPrice($price, $plan), 503);
            $subscription = $this->api()->withHeader('Idempotency-Key', 'dentiflow-debit-trial-'.$checkoutId)
                ->post('subscriptions', [
                    'customer' => $workspace->stripe_customer_id,
                    'items' => [['price' => $priceId, 'quantity' => 1]],
                    'default_payment_method' => $card['id'],
                    'payment_settings' => ['payment_method_types' => ['card']],
                    'trial_period_days' => 3,
                ])->throw()->json();

            $this->saveSubscription($workspace, $subscription, $plan);
        });
    }

    public function portal(ClinicWorkspace $workspace): string
    {
        abort_unless($workspace->stripe_customer_id, 404);
        $portal = $this->api()->post('billing_portal/sessions', [
            'customer' => $workspace->stripe_customer_id,
            'return_url' => route('clinic.plan'),
        ])->throw()->json();

        return $this->stripeUrl($portal['url'], 'billing.stripe.com');
    }

    public function sync(string $subscriptionId, ?string $checkoutId): void
    {
        if (! preg_match('/^sub_[A-Za-z0-9]+$/D', $subscriptionId)) {
            return;
        }

        DB::transaction(function () use ($subscriptionId, $checkoutId) {
            $workspace = ClinicWorkspace::query()
                ->where($checkoutId ? 'stripe_checkout_id' : 'stripe_subscription_id', $checkoutId ?? $subscriptionId)
                ->lockForUpdate()->first();
            if (! $workspace || ($workspace->stripe_subscription_id && $workspace->stripe_subscription_id !== $subscriptionId)) {
                return;
            }

            $subscription = $this->api()->get('subscriptions/'.$subscriptionId, ['expand' => ['latest_invoice']])->throw()->json();
            if ($workspace->stripe_customer_id && $subscription['customer'] !== $workspace->stripe_customer_id) {
                return;
            }
            $items = data_get($subscription, 'items.data', []);
            $price = $items[0]['price'] ?? [];
            $plan = array_search($price['id'] ?? null, config('stripe.prices'), true);
            if ($plan === false || count($items) !== 1 || ($items[0]['quantity'] ?? null) !== 1
                || ! $this->validPrice($price, $plan) || ($subscription['livemode'] ?? true)) {
                return;
            }

            $this->saveSubscription($workspace, $subscription + ['id' => $subscriptionId], $plan);
        });
    }

    private function saveSubscription(ClinicWorkspace $workspace, array $subscription, string $plan): void
    {
        $workspace->trial_plan = $plan;
        $workspace->stripe_subscription_id = $subscription['id'];
        $workspace->stripe_customer_id = $subscription['customer'];
        $workspace->stripe_status = $subscription['status'];
        if (! empty($subscription['trial_end'])) {
            $workspace->trial_ends_at = Carbon::createFromTimestamp($subscription['trial_end'], config('app.timezone'));
        }

        $paidUntil = data_get($subscription, 'items.data.0.current_period_end');
        $workspace->stripe_paid_until = $paidUntil && data_get($subscription, 'latest_invoice.status') === 'paid'
            && data_get($subscription, 'latest_invoice.amount_paid', 0) > 0
            ? Carbon::createFromTimestamp($paidUntil, config('app.timezone'))
            : null;
        $workspace->save();
    }

    private function validPrice(array $price, string $plan): bool
    {
        return ($price['livemode'] ?? true) === false
            && ($price['currency'] ?? null) === 'mxn'
            && ($price['unit_amount'] ?? null) === ClinicWorkspace::TRIAL_PLANS[$plan]['price'] * 100
            && data_get($price, 'recurring.interval') === 'month'
            && data_get($price, 'recurring.interval_count') === 1;
    }

    private function stripeUrl(string $url, string $host): string
    {
        abort_unless(Str::startsWith($url, "https://{$host}/"), 502);

        return $url;
    }

    private function api(): PendingRequest
    {
        $secret = (string) config('stripe.secret');
        abort_unless(config('stripe.enabled') && Str::startsWith($secret, ['sk_test_', 'rk_test_'])
            && Str::startsWith((string) config('stripe.webhook_secret'), 'whsec_'), 503, 'Stripe de prueba no está configurado.');

        return Http::asForm()->acceptJson()->withToken($secret)->baseUrl('https://api.stripe.com/v1/')
            ->withHeader('Stripe-Version', '2025-03-31.basil')->timeout(10);
    }
}

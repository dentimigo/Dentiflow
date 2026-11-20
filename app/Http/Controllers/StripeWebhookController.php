<?php

namespace App\Http\Controllers;

use App\Services\StripeClinicBilling;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeClinicBilling $billing)
    {
        $secret = (string) config('stripe.webhook_secret');
        abort_unless(config('stripe.enabled') && str_starts_with($secret, 'whsec_'), 503);

        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            abort(400, 'Firma de Stripe inválida.');
        }

        abort_unless($event->livemode === false, 400);

        $object = $event->data->object->toArray();
        if ($event->type === 'checkout.session.completed' && ($object['mode'] ?? null) === 'setup'
            && is_string($object['id'] ?? null)) {
            $billing->completeDebitSetup($object['id']);

            return response()->noContent();
        }
        $checkoutId = null;
        $subscriptionId = match ($event->type) {
            'checkout.session.completed' => ($object['mode'] ?? null) === 'subscription'
                && ($object['status'] ?? null) === 'complete' ? ($object['subscription'] ?? null) : null,
            'customer.subscription.updated', 'customer.subscription.deleted' => $object['id'] ?? null,
            'invoice.paid', 'invoice.payment_failed' => data_get($object, 'parent.subscription_details.subscription')
                ?? ($object['subscription'] ?? null),
            default => null,
        };

        if ($event->type === 'checkout.session.completed') {
            $checkoutId = $object['id'] ?? null;
            if (! is_string($checkoutId)) {
                return response()->noContent();
            }
        }
        if (is_string($subscriptionId)) {
            $billing->sync($subscriptionId, $checkoutId);
        }

        return response()->noContent();
    }
}

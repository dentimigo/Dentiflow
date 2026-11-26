<?php

namespace App\Http\Controllers;

use App\Models\ClinicWorkspace;
use App\Services\StripeClinicBilling;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ClinicPlanController extends Controller
{
    public function show(Request $request, StripeClinicBilling $billing)
    {
        $workspace = $this->workspace($request);
        if (! $workspace->trial_required) {
            return redirect()->route('inicio');
        }

        $isOwner = (int) $workspace->owner_user_id === (int) $request->user()->id;
        $stripeEnabled = (bool) config('stripe.enabled');
        $stripeConfirmationError = null;

        if ($isOwner && $stripeEnabled && $workspace->stripe_checkout_id && ! $workspace->stripe_subscription_id
            && $workspace->trial_ends_at === null) {
            try {
                $billing->completeDebitSetup($workspace->stripe_checkout_id);
                $workspace->refresh();
            } catch (Throwable $exception) {
                report($exception);
                $stripeConfirmationError = class_basename($exception);
                if ($exception instanceof RequestException) {
                    $stripeConfirmationError = 'Stripe HTTP '.$exception->response->status();
                    foreach (['error.code', 'error.param'] as $field) {
                        $value = $exception->response->json($field);
                        if (is_string($value) && preg_match('/^[a-z_][a-z0-9_.\[\]]{0,79}$/D', $value)) {
                            $stripeConfirmationError .= ' / '.$value;
                        }
                    }
                } elseif ($exception instanceof HttpExceptionInterface) {
                    $stripeConfirmationError .= ' HTTP '.$exception->getStatusCode();
                } elseif ($exception instanceof \Illuminate\Database\QueryException) {
                    $stripeConfirmationError .= ' SQLSTATE '.($exception->errorInfo[0] ?? 'unknown');
                }
            }
        }

        return view('clinic.plan', [
            'workspace' => $workspace,
            'isOwner' => $isOwner,
            'plans' => ClinicWorkspace::TRIAL_PLANS,
            'stripeEnabled' => $stripeEnabled,
            'stripeConfirmationError' => $stripeConfirmationError,
        ]);
    }

    public function start(Request $request, StripeClinicBilling $billing)
    {
        $workspace = $this->workspace($request);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 403);
        abort_unless($workspace->trial_required, 403);
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(ClinicWorkspace::TRIAL_PLANS))],
        ], [
            'plan.required' => 'Selecciona el plan que quieres probar.',
            'plan.in' => 'Elige uno de los planes disponibles.',
        ]);

        if (config('stripe.enabled')) {
            if ($workspace->hasWorkspaceAccess() && ! $workspace->stripe_subscription_id) {
                return redirect()->route('inicio');
            }

            return redirect()->away($billing->checkout($workspace, $data['plan'], $request->user()->email));
        }

        return DB::transaction(function () use ($workspace, $data) {
            $workspace = ClinicWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
            if ($workspace->trial_ends_at === null) {
                $plan = ClinicWorkspace::TRIAL_PLANS[$data['plan']];
                if ($workspace->members()->count() > $plan['seats']) {
                    return back()->withInput()->withErrors(['plan' => 'Elige un plan con espacio para todos los integrantes actuales.']);
                }

                $workspace->trial_plan = $data['plan'];
                $workspace->trial_ends_at = now()->addRealHours(72);
                $workspace->save();
            }

            return redirect()->route($workspace->hasWorkspaceAccess() ? 'inicio' : 'clinic.plan');
        });
    }

    public function portal(Request $request, StripeClinicBilling $billing)
    {
        $workspace = $this->workspace($request);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 403);

        $returnRoute = $workspace->hasWorkspaceAccess() ? 'perfil' : 'clinic.plan';

        try {
            return redirect()->away($billing->portal($workspace, route($returnRoute)));
        } catch (\Illuminate\Http\Client\HttpClientException $exception) {
            report($exception);
            $reference = $exception instanceof RequestException
                ? 'Stripe HTTP '.$exception->response->status()
                : 'Conexión con Stripe';

            return redirect()->route($returnRoute)->withErrors([
                'portal' => 'No se pudo abrir el portal de Stripe. La cancelación no se realizó desde DentiFlow. Referencia: '.$reference,
            ], 'subscription');
        }
    }

    public function cancel(Request $request, StripeClinicBilling $billing)
    {
        $workspace = $this->workspace($request);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 403);
        $request->validateWithBag('subscription', ['confirm_cancel' => ['accepted']], [
            'confirm_cancel.accepted' => 'Confirma que deseas cancelar la renovación.',
        ]);

        try {
            $billing->cancelRenewal($workspace);
        } catch (\Illuminate\Http\Client\HttpClientException | HttpExceptionInterface $exception) {
            report($exception);
            $reference = match (true) {
                $exception instanceof RequestException => 'Stripe HTTP '.$exception->response->status(),
                $exception instanceof HttpExceptionInterface => 'DentiFlow HTTP '.$exception->getStatusCode(),
                default => 'Conexión con Stripe',
            };
            if ($exception instanceof RequestException) {
                $requestId = $exception->response->header('Request-Id');
                if (preg_match('/^req_[A-Za-z0-9]+$/D', $requestId)) {
                    $reference .= ' / '.$requestId;
                }
            }

            return redirect()->route('perfil')->withErrors([
                'cancel' => 'No pudimos confirmar la cancelación con Stripe. Referencia: '.$reference,
            ], 'subscription');
        }

        return redirect()->route('perfil')->with('subscription_status', 'La renovación quedó cancelada en Stripe.');
    }

    public function change(Request $request, StripeClinicBilling $billing)
    {
        $workspace = $this->workspace($request);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 403);
        $data = $request->validateWithBag('subscription', [
            'plan' => ['required', Rule::in(array_keys(ClinicWorkspace::TRIAL_PLANS))],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Confirma el cambio al plan seleccionado.',
        ]);

        try {
            $billing->changePlan($workspace, $data['plan']);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->route('perfil')->withErrors($exception->errors(), 'subscription');
        } catch (\Illuminate\Http\Client\HttpClientException $exception) {
            report($exception);

            return redirect()->route('perfil')->withErrors([
                'plan' => 'No pudimos confirmar el cambio con Stripe. Revisa el plan actual antes de volver a intentarlo.',
            ], 'subscription');
        }

        return redirect()->route('perfil')->with('subscription_status', 'Tu plan se actualizó.');
    }

    private function workspace(Request $request): ClinicWorkspace
    {
        $workspace = $request->attributes->get('clinic_workspace');
        abort_unless($workspace instanceof ClinicWorkspace && $request->user(), 403);

        return $workspace;
    }
}

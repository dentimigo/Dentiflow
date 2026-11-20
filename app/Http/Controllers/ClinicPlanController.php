<?php

namespace App\Http\Controllers;

use App\Models\ClinicWorkspace;
use App\Services\StripeClinicBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClinicPlanController extends Controller
{
    public function show(Request $request)
    {
        $workspace = $this->workspace($request);
        if (! $workspace->trial_required) {
            return redirect()->route('inicio');
        }

        return view('clinic.plan', [
            'workspace' => $workspace,
            'isOwner' => (int) $workspace->owner_user_id === (int) $request->user()->id,
            'plans' => ClinicWorkspace::TRIAL_PLANS,
            'stripeEnabled' => (bool) config('stripe.enabled'),
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

        return redirect()->away($billing->portal($workspace, route($returnRoute)));
    }

    public function change(Request $request, StripeClinicBilling $billing)
    {
        $workspace = $this->workspace($request);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 403);
        $data = $request->validateWithBag('subscription', [
            'plan' => ['required', Rule::in(array_keys(ClinicWorkspace::TRIAL_PLANS))],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Confirma que aceptas el precio mensual del plan seleccionado.',
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

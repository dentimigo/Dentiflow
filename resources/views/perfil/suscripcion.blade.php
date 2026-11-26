@php
    $plans = \App\Models\ClinicWorkspace::TRIAL_PLANS;
    $currentPlan = $plans[$workspace->trial_plan] ?? null;
    $members = $workspace->members()->count();
    $trialing = $workspace->stripe_status === 'trialing' || (! $workspace->stripe_subscription_id && $workspace->trial_ends_at?->isFuture());
    $until = $trialing ? $workspace->trial_ends_at : $workspace->stripe_paid_until;
    $canManage = config('stripe.enabled') && $workspace->stripe_customer_id;
    $trialWithoutCard = $workspace->hasTrialWithoutCard();
@endphp

<section class="mt-6 rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]" aria-labelledby="subscription-heading">
    <h2 id="subscription-heading" class="text-xl font-bold text-slate-900">Suscripción</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $workspace->name }}</p>

    @if (session('subscription_status'))
        <p role="status" class="mt-4 text-sm font-semibold text-green-700">{{ session('subscription_status') }}</p>
    @endif
    @foreach ($errors->subscription->all() as $error)
        <p role="alert" class="mt-4 text-sm font-semibold text-red-700">{{ $error }}</p>
    @endforeach

    <dl class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl bg-[#fbf0ff] p-4">
            <dt class="text-sm text-slate-600">Plan actual</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $currentPlan['name'] ?? 'Sin plan de pago' }}</dd>
            @if ($currentPlan)
                <dd class="text-sm text-slate-600">${{ $currentPlan['price'] }} MXN al mes</dd>
            @endif
        </div>
        <div class="rounded-2xl bg-[#fbf0ff] p-4">
            <dt class="text-sm text-slate-600">Integrantes, incluido el titular</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $members }}{{ $workspace->seatLimit() !== null ? ' de '.$workspace->seatLimit() : '' }}</dd>
        </div>
        <div class="rounded-2xl bg-[#fbf0ff] p-4">
            <dt class="text-sm text-slate-600">Estado</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $trialing ? 'Prueba activa' : ($workspace->stripe_subscription_id ? 'Suscripción activa' : 'Acceso habilitado') }}</dd>
            @if ($until)
                <dd class="text-sm text-slate-600">{{ $trialing ? 'La prueba termina el' : 'Periodo pagado hasta el' }} {{ $until->format('d/m/Y H:i') }}</dd>
            @endif
        </div>
    </dl>

    @if ($canManage)
        <form method="POST" action="{{ route('clinic.plan.portal') }}" class="mt-5">
            @csrf
            <button class="font-semibold text-[#9700cf] underline">Gestionar tarjeta, facturas o cancelación en Stripe</button>
        </form>
    @endif

    @if ($canManage && $workspace->stripe_subscription_id && $until && $workspace->hasWorkspaceAccess())
        @if ($workspace->stripe_cancellation_pending)
            <p class="mt-5 text-sm text-slate-700">Renovación cancelada. Conservas el acceso hasta el {{ $until->format('d/m/Y H:i') }}.</p>
        @else
            <form method="POST" action="{{ route('perfil.subscription.cancel') }}" class="mt-5 space-y-3 border-t border-[#ead2fb] pt-5">
                @csrf
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="confirm_cancel" value="1" required>
                    Confirmo que quiero cancelar la renovación. El acceso termina el {{ $until->format('d/m/Y H:i') }}.
                </label>
                <button class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-700">Cancelar suscripción</button>
            </form>
        @endif
    @endif

    @if ($currentPlan && ! $workspace->stripe_cancellation_pending && ($trialWithoutCard || ($canManage && $workspace->stripe_subscription_id)))
        <form method="POST" action="{{ route('perfil.subscription.change') }}" class="mt-5 space-y-3 border-t border-[#ead2fb] pt-5">
            @csrf
            <label for="subscription-plan" class="block font-semibold text-slate-900">Cambiar plan</label>
            <select id="subscription-plan" name="plan" required class="w-full rounded-xl border border-[#ead2fb] p-3 text-sm sm:w-auto">
                <option value="">Selecciona un plan</option>
                @foreach ($plans as $key => $plan)
                    @continue($key === $workspace->trial_plan)
                    <option value="{{ $key }}" @disabled($members > $plan['seats'])>
                        {{ $plan['name'] }} · ${{ $plan['price'] }} MXN al mes · {{ $plan['seats'] }} personas{{ $members > $plan['seats'] ? ' · Sin cupo para el equipo actual' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="text-sm text-slate-600">{{ $trialWithoutCard
                ? 'El cupo cambia al confirmar. Tu prueba conserva la misma fecha de vencimiento. No se registra una tarjeta ni se hace un cobro.'
                : 'El cupo cambia al confirmar. La tarifa nueva se aplica en el siguiente cobro y la fecha de cobro se conserva.' }}</p>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="confirm" value="1" required>
                {{ $trialWithoutCard ? 'Confirmo el cambio de plan durante mi prueba.' : 'Acepto el precio mensual del plan seleccionado.' }}
            </label>
            <button class="rounded-xl bg-[#9700cf] px-4 py-2 text-sm font-bold text-white">Confirmar cambio</button>
        </form>
    @endif
</section>

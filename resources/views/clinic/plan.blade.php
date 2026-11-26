<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plan de la agenda · DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fbf0ff] text-[#21152e]">
    <main class="mx-auto max-w-3xl px-5 py-10 sm:py-16">
        <header class="mb-7 flex items-center justify-between gap-4">
            <span class="text-lg font-bold text-[#512569]">DentiFlow</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm font-bold text-[#512569] underline">Cerrar sesión</button>
            </form>
        </header>
        <section class="rounded-2xl border border-[#ead2fb] bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-[#512569]">{{ $workspace->name }}</p>
            @if ($isOwner && $stripeEnabled && $workspace->stripe_subscription_id && $workspace->hasWorkspaceAccess())
                <form method="POST" action="{{ route('clinic.plan.portal') }}" class="mt-3">
                    @csrf
                    <button class="text-sm font-bold text-[#512569] underline">Gestionar o cancelar suscripción</button>
                </form>
            @endif
            @if ($workspace->stripe_status === 'active' && $workspace->hasWorkspaceAccess())
                <h1 class="mt-2 text-3xl font-bold">Tu suscripción está activa</h1>
                <a href="{{ route('inicio') }}" class="mt-5 inline-block rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Abrir agenda</a>
            @elseif ($workspace->hasWorkspaceAccess())
                <h1 class="mt-2 text-3xl font-bold">Tu prueba está activa</h1>
                <p class="mt-3 text-sm text-slate-600">Termina el {{ $workspace->trial_ends_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }}).</p>
                <a href="{{ route('inicio') }}" class="mt-5 inline-block rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Abrir agenda</a>
            @elseif (! $isOwner)
                <h1 class="mt-2 text-3xl font-bold">El consultorio está bloqueado</h1>
                <p class="mt-3 text-sm text-slate-600">Pídele al titular del consultorio que reactive el plan para recuperar el acceso.</p>
            @else
                @php
                    $renewing = $workspace->trial_ends_at !== null || in_array($workspace->stripe_status, ['canceled', 'incomplete_expired'], true);
                    $hasExistingSubscription = $workspace->hasRecoverableStripeSubscription();
                @endphp
                <h1 class="mt-2 text-3xl font-bold">{{ $renewing || $hasExistingSubscription ? 'Renueva tu plan' : 'Elige el plan que quieres probar' }}</h1>
                @if ($renewing || $hasExistingSubscription)
                    <p class="mt-3 text-sm text-slate-600">Tus citas y expedientes siguen guardados. Reactiva tu suscripción para volver a usar el consultorio.</p>
                @endif
                @if (($renewing || $hasExistingSubscription) && ! $stripeEnabled)
                    @if ($workspace->trial_ends_at && ! $workspace->stripe_status)
                        <p class="mt-3 text-sm text-slate-600">Tu prueba de 3 días terminó.</p>
                    @endif
                    <p class="mt-3 text-sm text-slate-600">El pago para reactivar la agenda aún no está disponible.</p>
                @elseif ($hasExistingSubscription)
                    @if ($workspace->stripe_cancellation_pending)
                        <p class="mt-3 text-sm text-slate-600">Tu suscripción tiene una cancelación programada. Para recuperar el acceso, reanuda tu suscripción en Stripe y liquida cualquier pago pendiente.</p>
                    @else
                        <p class="mt-3 text-sm text-slate-600">Actualiza tu tarjeta de débito o crédito en Stripe y revisa si tienes un pago pendiente. El acceso se restablece cuando Stripe confirma el pago.</p>
                    @endif
                    <form method="POST" action="{{ route('clinic.plan.portal') }}" class="mt-5">
                        @csrf
                        <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">{{ $workspace->stripe_cancellation_pending ? 'Gestionar cancelación en Stripe' : 'Actualizar tarjeta de débito o crédito' }}</button>
                    </form>
                @else
                    @if ($stripeEnabled && ! $renewing)
                        <p class="mt-3 text-sm text-slate-600">Hoy pagas $0. Registra una tarjeta de débito en Stripe para iniciar tu prueba de 72 horas. Al terminar, se cobra el plan seleccionado cada mes hasta que canceles.</p>
                        @unless (config('stripe.live'))
                            <p class="mt-3 text-sm text-slate-600">Stripe está en modo de prueba. En el formulario de Stripe, usa la Visa de débito de prueba <strong class="whitespace-nowrap">4000 0566 5566 5556</strong>, vencimiento <strong>12/34</strong> y CVC <strong>123</strong>. No habrá cobros reales.</p>
                        @endunless
                    @elseif ($stripeEnabled)
                        <p class="mt-3 text-sm text-slate-600">Elige un plan y paga con tarjeta de débito o crédito en Stripe. El acceso se restablece cuando Stripe confirma el primer cobro.</p>
                    @else
                        <p class="mt-3 text-sm text-slate-600">Hoy pagas $0. La prueba dura 72 horas desde que confirmas. El precio mensual aplica si decides continuar después.</p>
                    @endif
                    @if ($stripeConfirmationError)
                        <p class="mt-3 text-sm text-fuchsia-700" role="alert">Hubo un problema al verificar tu registro con Stripe. Referencia: <code>{{ $stripeConfirmationError }}</code></p>
                        <a class="mt-5 inline-block rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white" href="{{ route('clinic.plan', ['stripe' => 'regreso']) }}">Reintentar confirmación</a>
                    @elseif ($stripeEnabled && $workspace->stripe_status === 'debit_required')
                        <p class="mt-3 text-sm text-fuchsia-700" role="alert">Stripe no pudo confirmar que la tarjeta fuera de débito. Intenta con otra tarjeta de débito para iniciar tu prueba.</p>
                    @elseif ($stripeEnabled && request('stripe') === 'regreso')
                        <p class="mt-3 text-sm text-slate-600">Estamos esperando la confirmación de Stripe. Actualiza esta página en unos segundos.</p>
                    @endif
                    @unless ($stripeConfirmationError)
                    <form method="POST" action="{{ route('clinic.plan.start') }}" class="mt-6">
                        @csrf
                        @error('plan')
                            <p class="mb-4 rounded-xl bg-fuchsia-50 p-3 text-sm text-fuchsia-700" role="alert">{{ $message }}</p>
                        @enderror
                        <fieldset class="grid gap-3 sm:grid-cols-2">
                            <legend class="sr-only">Planes disponibles</legend>
                            @foreach ($plans as $key => $plan)
                                <button type="submit" name="plan" value="{{ $key }}" class="rounded-xl border border-[#ead2fb] p-4 text-left hover:bg-fuchsia-50">
                                    <span class="block font-bold">{{ $plan['name'] }}</span>
                                    <span class="mt-1 block text-sm">${{ $plan['price'] }} MXN al mes</span>
                                    <span class="mt-1 block text-sm text-slate-600">Hasta {{ $plan['seats'] }} personas, incluido el titular</span>
                                    <span class="mt-3 block text-sm font-bold text-[#9700cf]">{{ $stripeEnabled ? ($renewing ? 'Renovar plan en Stripe' : 'Registrar débito e iniciar prueba') : 'Iniciar mis 3 días gratis' }}</span>
                                </button>
                            @endforeach
                        </fieldset>
                    </form>
                    @endunless
                @endif
            @endif
        </section>
    </main>
</body>
</html>

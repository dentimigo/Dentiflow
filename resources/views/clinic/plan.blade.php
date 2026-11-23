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
                <h1 class="mt-2 text-3xl font-bold">La agenda está bloqueada</h1>
                <p class="mt-3 text-sm text-slate-600">Pídele al titular del consultorio que active o renueve el plan para volver a agendar citas y hacer cambios.</p>
            @else
                @php
                    $renewing = $workspace->trial_ends_at !== null || $workspace->stripe_status === 'canceled';
                    $hasExistingSubscription = $workspace->stripe_subscription_id && $workspace->stripe_status !== 'canceled';
                @endphp
                <h1 class="mt-2 text-3xl font-bold">{{ $renewing || $hasExistingSubscription ? 'Renueva tu plan' : 'Elige el plan que quieres probar' }}</h1>
                @if ($renewing || $hasExistingSubscription)
                    <p class="mt-3 text-sm text-slate-600">Tus citas y expedientes siguen guardados. Para agendar o hacer cambios, primero debes reactivar tu suscripción.</p>
                @endif
                @if (($renewing || $hasExistingSubscription) && ! $stripeEnabled)
                    @if ($workspace->trial_ends_at && ! $workspace->stripe_status)
                        <p class="mt-3 text-sm text-slate-600">Tu prueba de 3 días terminó.</p>
                    @endif
                    <p class="mt-3 text-sm text-slate-600">El pago para reactivar la agenda aún no está disponible.</p>
                @elseif ($hasExistingSubscription)
                    <p class="mt-3 text-sm text-slate-600">Si quitaste tu tarjeta o el cobro falló, vuelve a agregar una tarjeta de débito en Stripe y revisa si hay un pago pendiente. La agenda se habilitará cuando Stripe confirme el pago.</p>
                    <form method="POST" action="{{ route('clinic.plan.portal') }}" class="mt-5">
                        @csrf
                        <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Actualizar tarjeta de débito</button>
                    </form>
                @else
                    @if ($stripeEnabled && ! $renewing)
                        <p class="mt-3 text-sm text-slate-600">Hoy pagas $0. Registra una tarjeta de débito en Stripe para iniciar tu prueba de 72 horas. Al terminar, se cobra el plan seleccionado cada mes hasta que canceles.</p>
                    @elseif ($stripeEnabled)
                        <p class="mt-3 text-sm text-slate-600">Elige un plan y vuelve a registrar tu tarjeta de débito en Stripe. El primer cobro se realiza al confirmar y la agenda se habilita cuando Stripe confirma el pago.</p>
                    @else
                        <p class="mt-3 text-sm text-slate-600">Hoy pagas $0. La prueba dura 72 horas desde que confirmas. El precio mensual aplica si decides continuar después.</p>
                    @endif
                    @if ($stripeEnabled && $workspace->stripe_status === 'debit_required')
                        <p class="mt-3 text-sm text-fuchsia-700" role="alert">Stripe no pudo confirmar que la tarjeta fuera de débito. Intenta con otra tarjeta de débito para iniciar tu prueba.</p>
                    @elseif ($stripeEnabled && request('stripe') === 'regreso')
                        <p class="mt-3 text-sm text-slate-600">Estamos esperando la confirmación de Stripe. Actualiza esta página en unos segundos.</p>
                    @endif
                    <form method="POST" action="{{ route('clinic.plan.start') }}" class="mt-6">
                        @csrf
                        @error('plan')
                            <p class="mb-4 rounded-xl bg-fuchsia-50 p-3 text-sm text-fuchsia-700" role="alert">{{ $message }}</p>
                        @enderror
                        <fieldset class="grid gap-3 sm:grid-cols-2">
                            <legend class="sr-only">Planes disponibles</legend>
                            @foreach ($plans as $key => $plan)
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-[#ead2fb] p-4">
                                    <input class="mt-1" type="radio" name="plan" value="{{ $key }}" required @checked(old('plan') === $key)>
                                    <span>
                                        <span class="block font-bold">{{ $plan['name'] }}</span>
                                        <span class="mt-1 block text-sm">${{ $plan['price'] }} MXN al mes</span>
                                        <span class="mt-1 block text-sm text-slate-600">Hasta {{ $plan['seats'] }} personas, incluido el titular</span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <button class="mt-5 rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">{{ $stripeEnabled ? ($renewing ? 'Renovar plan en Stripe' : 'Registrar débito e iniciar prueba') : 'Iniciar mis 3 días gratis' }}</button>
                    </form>
                @endif
            @endif
        </section>
    </main>
</body>
</html>

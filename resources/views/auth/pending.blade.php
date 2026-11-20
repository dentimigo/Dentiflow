<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de espera - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
@php
    $name = session('pending_name');
    $email = session('pending_email');
    $requestedRole = session('pending_requested_role');
    $requestedRoleLabel = $requestedRole === 'doctor' ? 'Doctor' : ($requestedRole === 'asistente' ? 'Asistente' : null);
@endphp
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
<main class="flex min-h-screen items-center justify-center px-5 py-10">
    <section class="w-full max-w-4xl overflow-hidden rounded-[1.75rem] border border-[#ead2fb] bg-white shadow-[0_28px_90px_rgba(81,37,105,.14)]">
        <div class="relative overflow-hidden bg-[#512569] px-6 py-8 text-white sm:px-10 sm:py-10">
            <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-[#9700cf]/30 blur-2xl"></div>
            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#9700cf] shadow-lg shadow-[#21152e]/30">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7.3 3.7c1.8-.8 3 .3 4.7.3s2.9-1.1 4.7-.3c2.6 1.2 3 4.8 1.9 7.3-.9 2.1-1.4 3.4-1.6 6.6-.1 1.7-.9 3.4-2.1 3.4-1.7 0-1.2-4.8-2.9-4.8S10.8 21 9.1 21C7.9 21 7.1 19.3 7 17.6c-.2-3.2-.7-4.5-1.6-6.6C4.3 8.5 4.7 4.9 7.3 3.7Z"/></svg>
                    </span>
                    <div>
                        <p class="text-base font-bold leading-none">DentiFlow</p>
                        <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.22em] text-white/50">Sala de espera</p>
                    </div>
                </div>

                <p class="mt-10 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-[#ead2fb]">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-amber-300"></span>
                    Revisión pendiente
                </p>
                <h1 class="mt-5 max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">¡Recibimos tu solicitud!</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-white/70">Estás en la sala de espera de DentiFlow. Para proteger la información clínica, Administración revisará tus datos antes de permitir el acceso.</p>
            </div>
        </div>

        <div class="grid gap-7 p-6 sm:p-10 lg:grid-cols-[1fr_280px]">
            <div class="space-y-5">
                @if($name || $email)
                    <div class="rounded-2xl border border-[#ead2fb] bg-[#fbf0ff] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#ad79d3]">Solicitud registrada</p>
                        @if($name)<p class="mt-2 text-lg font-bold text-[#21152e]">{{ $name }}</p>@endif
                        @if($email)<p class="mt-1 text-sm font-semibold text-[#6b5778]">{{ $email }}</p>@endif
                        @if($requestedRoleLabel)
                            <p class="mt-3 inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold text-[#9700cf] shadow-sm">Acceso solicitado: {{ $requestedRoleLabel }}</p>
                        @endif
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-bold text-slate-900">¿Qué sigue?</h2>
                    <ol class="mt-4 space-y-4 text-sm leading-6 text-slate-500">
                        <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#f6e8ff] text-xs font-bold text-[#9700cf]">1</span><span>Administración revisa tu solicitud.</span></li>
                        <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#f6e8ff] text-xs font-bold text-[#9700cf]">2</span><span>Te asigna el rol y los permisos correctos.</span></li>
                        <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#f6e8ff] text-xs font-bold text-[#9700cf]">3</span><span>Ingresas con el mismo correo y contraseña que registraste.</span></li>
                    </ol>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-[#ead2fb] bg-[#f6e8ff] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#9700cf]">Estado de tu cuenta</p>
                    <p class="mt-3 text-2xl font-bold text-[#512569]">Pendiente</p>
                    <p class="mt-2 text-xs leading-5 text-[#6b5778]">No necesitas registrarte otra vez. Tu cuenta ya existe y está protegida.</p>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                    <p class="text-sm font-bold text-emerald-800">Tus datos están seguros</p>
                    <p class="mt-1 text-xs leading-5 text-emerald-700">Nadie puede acceder con tu cuenta mientras siga pendiente.</p>
                </div>

                <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">Volver al inicio de sesión</a>
            </aside>
        </div>
    </section>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta - DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#fbf0ff] font-['DM_Sans'] text-[#21152e] antialiased">
@php
    $roles = [
        [
            'key' => 'doctor',
            'title' => 'Doctor',
            'label' => 'Área clínica',
            'description' => 'Agenda, expedientes clínicos, tratamientos y seguimiento de pacientes.',
            'accent' => '#a800f4',
            'soft' => '#f6e8ff',
            'icon' => 'doctor',
        ],
        [
            'key' => 'asistente',
            'title' => 'Asistente',
            'label' => 'Recepción',
            'description' => 'Citas, calendario, pacientes, inventario y coordinación operativa.',
            'accent' => '#a126bb',
            'soft' => '#fbf0ff',
            'icon' => 'calendar',
        ],
    ];
@endphp

<main class="min-h-screen lg:grid lg:grid-cols-[minmax(360px,42%)_1fr]">
    <section class="relative overflow-hidden bg-[#512569] px-6 py-8 text-white sm:px-10 lg:min-h-screen lg:px-12 lg:py-10">
        <div class="absolute inset-0 opacity-30" style="background-image: linear-gradient(135deg, rgba(234,210,251,.22) 0 1px, transparent 1px), linear-gradient(45deg, rgba(151,0,207,.18) 0 1px, transparent 1px); background-size: 42px 42px;"></div>
        <div class="absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-[#21152e] to-transparent"></div>

        <div class="relative z-10 flex min-h-[420px] flex-col lg:min-h-[calc(100vh-5rem)]">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#9700cf] shadow-lg shadow-[#21152e]/30">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7.3 3.7c1.8-.8 3 .3 4.7.3s2.9-1.1 4.7-.3c2.6 1.2 3 4.8 1.9 7.3-.9 2.1-1.4 3.4-1.6 6.6-.1 1.7-.9 3.4-2.1 3.4-1.7 0-1.2-4.8-2.9-4.8S10.8 21 9.1 21C7.9 21 7.1 19.3 7 17.6c-.2-3.2-.7-4.5-1.6-6.6C4.3 8.5 4.7 4.9 7.3 3.7Z"/></svg>
                    </span>
                    <div>
                        <p class="text-base font-bold leading-none">DentiFlow</p>
                        <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.22em] text-white/45">Gestión clínica</p>
                    </div>
                </div>
                <span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-[#ead2fb]">Acceso seguro</span>
            </header>

            <div class="flex flex-1 flex-col justify-center py-14 lg:py-16">
                <div class="mx-auto max-w-lg text-left">
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-[#ead2fb]">Crear cuenta</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight sm:text-5xl">¿Qué tipo de cuenta deseas crear?</h1>
                    <p class="mt-5 max-w-md text-sm leading-7 text-white/62">Elige Doctor o Asistente. Tu cuenta se registrará directamente con ese rol.</p>
                </div>
            </div>

        </div>
    </section>

    <section class="flex min-h-screen items-center px-5 py-10 sm:px-8 lg:px-12">
        <div class="mx-auto w-full max-w-4xl">
            <a href="{{ route('login') }}" class="mb-8 inline-flex items-center gap-2 text-sm font-semibold text-[#9700cf] transition hover:text-[#512569]">
                <span aria-hidden="true">&larr;</span>
                Volver al inicio de sesi&oacute;n
            </a>

            @if(session('warning'))
                <div class="mb-6 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">{{ session('warning') }}</div>
            @endif
            @include('partials.flash-success')

            <div class="grid gap-4 md:grid-cols-2">
                @foreach($roles as $role)
                    <a href="{{ route('register', ['role' => $role['key']]) }}" class="group relative overflow-hidden rounded-[1.35rem] border border-[#ead2fb] bg-white p-5 shadow-[0_18px_60px_rgba(81,37,105,.08)] transition duration-300 hover:-translate-y-1 hover:border-[#b27acc] hover:shadow-[0_24px_80px_rgba(81,37,105,.16)]">
                        <div class="absolute inset-x-0 top-0 h-1.5" style="background: linear-gradient(90deg, {{ $role['accent'] }}, #d3a2f2);"></div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl" style="background: {{ $role['soft'] }}; color: {{ $role['accent'] }};">
                                @if($role['icon'] === 'grid')
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                                @elseif($role['icon'] === 'doctor')
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M5 22v-3a7 7 0 0 1 14 0v3M9 7h6M12 4v6"/></svg>
                                @else
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2ZM7 2v4m10-4v4M3 9h18M8 14h3m2 0h3m-8 3h3"/></svg>
                                @endif
                            </span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full border border-[#ead2fb] text-[#b27acc] transition group-hover:translate-x-1 group-hover:border-transparent group-hover:bg-[#9700cf] group-hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </span>
                        </div>

                        <div class="mt-6">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xl font-bold text-[#21152e]">{{ $role['title'] }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider" style="background: {{ $role['soft'] }}; color: {{ $role['accent'] }};">{{ $role['label'] }}</span>
                            </div>
                            <p class="mt-3 min-h-[72px] text-sm leading-6 text-[#6b5778]">{{ $role['description'] }}</p>
                        </div>

                        <div class="mt-5 flex items-center justify-end border-t border-[#ead2fb] pt-4">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[#ad79d3]">Crear cuenta</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <footer class="mt-8 flex flex-col gap-2 text-xs text-[#8a7399] sm:flex-row sm:items-center sm:justify-between">
                <span>© {{ date('Y') }} DentiFlow</span>
                <span class="font-semibold text-[#512569]">Acceso para personal autorizado</span>
            </footer>
        </div>
    </section>
</main>
</body>
</html>


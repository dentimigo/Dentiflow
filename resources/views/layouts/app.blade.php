<!DOCTYPE html>
<html lang="es" style="margin:0;padding:0;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DentiFlow') · DentiFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;
            height: 100%;
            min-height: 100%;
            overflow: hidden;
        }

        #app-shell {
            margin: 0 !important;
            padding-top: 0 !important;
            position: relative;
            top: 0;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        #content-shell {
            position: fixed;
            inset: 0;
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;
            height: 100vh;
            height: 100dvh;
            overflow-y: auto;
            overflow-x: hidden;
        }

        #content-shell > header {
            position: sticky !important;
            top: 0 !important;
            margin: 0 !important;
        }

        @media (min-width: 1024px) {
            #sidebar {
                position: fixed !important;
                inset: 0 auto 0 0 !important;
                width: 268px;
                transform: none !important;
            }

            #content-shell {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 268px;
                margin-left: 0;
                width: calc(100% - 268px);
                height: 100vh;
                height: 100dvh;
                overflow-y: auto;
                overflow-x: hidden;
            }

            #content-shell > header {
                margin-top: 0 !important;
                top: 0 !important;
            }
        }
    </style>
</head>
<body class="m-0 bg-[#f6e8ff] font-['DM_Sans'] text-slate-900 antialiased" style="margin:0;padding:0;">
@php
    $currentRole = session('access_role', 'asistente');
    $currentGender = session('access_gender');
    $clinicSettings = \App\Models\ClinicSetting::current();
    $clinicName = $clinicSettings->clinic_name;
    $roleLabels = ['superadmin' => 'Superadministrador', 'admin' => 'Administrador', 'doctor' => $currentGender === 'mujer' ? 'Doctora' : 'Doctor', 'asistente' => 'Asistente'];
    $allNavigation = [
        ['route' => 'inicio', 'match' => 'inicio', 'label' => $currentRole === 'superadmin' ? 'Estado del sistema' : 'Resumen', 'icon' => 'grid', 'roles' => ['superadmin', 'admin', 'doctor', 'asistente']],
        ['route' => 'calendario', 'match' => 'calendario', 'label' => 'Calendario', 'icon' => 'calendar'],
        ['route' => 'citas', 'match' => 'citas*', 'label' => 'Citas', 'icon' => 'clock'],
        ['route' => 'pacientes', 'match' => 'pacientes', 'label' => 'Pacientes', 'icon' => 'users'],
        ['route' => 'expedientes', 'match' => ['expedientes*', 'pacientes.expediente'], 'label' => 'Expedientes', 'icon' => 'badge'],
        ['route' => 'pacientes.tratamientos', 'match' => 'pacientes.tratamientos*', 'label' => 'Tratamientos', 'icon' => 'sparkles'],
        ['route' => 'inventario', 'match' => 'inventario*', 'label' => 'Inventario', 'icon' => 'box'],
        ['route' => 'reportes', 'match' => 'reportes*', 'label' => 'Reportes', 'icon' => 'chart', 'roles' => ['doctor']],
        ['route' => 'admin.calendar', 'match' => 'admin.calendar', 'label' => 'Calendario', 'icon' => 'calendar', 'roles' => ['superadmin']],
        ['route' => 'admin.reports', 'match' => 'admin.reports*', 'label' => 'Reportes', 'icon' => 'chart', 'roles' => ['superadmin']],
        ['route' => 'admin.users', 'match' => 'admin.users*', 'label' => 'Usuarios', 'icon' => 'users', 'roles' => ['admin']],
        ['route' => 'admin.system', 'match' => 'admin.system*', 'label' => 'Sistema', 'icon' => 'settings', 'roles' => ['superadmin']],
        ['route' => 'admin.preferences', 'match' => ['admin.preferences*', 'admin.settings*'], 'label' => 'Preferencias', 'icon' => 'settings', 'roles' => ['superadmin']],
    ];
    $roleNavigation = [
        'superadmin' => ['inicio', 'admin.system', 'admin.preferences'],
        'admin' => ['inicio', 'calendario', 'citas', 'pacientes', 'expedientes', 'pacientes.tratamientos', 'inventario', 'reportes', 'admin.users'],
        'doctor' => ['inicio', 'calendario', 'citas', 'pacientes', 'expedientes', 'pacientes.tratamientos', 'reportes'],
        'asistente' => $clinicSettings->assistantAllowedSections(),
    ];
    $navigation = collect($allNavigation)->filter(fn ($item) => in_array($item['route'], $roleNavigation[$currentRole] ?? []));
@endphp
<div id="app-shell" class="h-screen overflow-hidden lg:flex" style="margin:0;padding-top:0;">
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/40 backdrop-blur-sm lg:hidden"></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[min(268px,86vw)] -translate-x-full flex-col bg-[#512569] text-white shadow-2xl transition-transform duration-300 lg:h-screen lg:w-[268px] lg:translate-x-0">
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
            <a href="{{ route('inicio') }}" class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#9700cf] shadow-lg shadow-[#512569]/30 transition hover:bg-[#a800e6]" aria-label="Ir al resumen" title="Ir al resumen">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7.3 3.7c1.8-.8 3 .3 4.7.3s2.9-1.1 4.7-.3c2.6 1.2 3 4.8 1.9 7.3-.9 2.1-1.4 3.4-1.6 6.6-.1 1.7-.9 3.4-2.1 3.4-1.7 0-1.2-4.8-2.9-4.8S10.8 21 9.1 21C7.9 21 7.1 19.3 7 17.6c-.2-3.2-.7-4.5-1.6-6.6C4.3 8.5 4.7 4.9 7.3 3.7Z"/></svg>
            </a>
            <div><p class="text-lg font-bold tracking-tight">DentiFlow</p><p class="text-[10px] font-semibold uppercase tracking-[.2em] text-white/40">Gestión clínica</p></div>
            <button id="sidebar-close" class="ml-auto rounded-lg p-2 text-white/50 hover:bg-white/10 lg:hidden" aria-label="Cerrar menú">×</button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-6">
            <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[.18em] text-white/35">Administración</p>
            <nav class="space-y-1">
                @foreach($navigation as $item)
                    @php $active = request()->routeIs($item['match']); @endphp
                    <a href="{{ route($item['route']) }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-[#9700cf] text-white shadow-lg shadow-[#512569]/20' : 'text-[#a9a7bb] hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-[19px] w-[19px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            @switch($item['icon'])
                                @case('grid') <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/> @break
                                @case('calendar') <path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2ZM7 2v4m10-4v4M3 9h18"/> @break
                                @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
                                @case('users') <path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6m2 17v-2a4 4 0 0 0-3-3.8"/> @break
                                @case('sparkles') <path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Zm6 10 .8 2.2L21 16l-2.2.8L18 19l-.8-2.2L15 16l2.2-.8L18 13ZM5 14l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3Z"/> @break
                                @case('badge') <circle cx="12" cy="8" r="4"/><path d="M5 22v-3a7 7 0 0 1 14 0v3M9 8h6M12 5v6"/> @break
                                @case('box') <path d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"/> @break
                                @case('chart') <path d="M4 20V10m6 10V4m6 16v-7m5 7H2"/> @break
                                @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/> @break
                            @endswitch
                        </svg>
                        {{ $item['label'] }}
                        @if($active)<span class="ml-auto h-1.5 w-1.5 rounded-full bg-white"></span>@endif
                    </a>
                @endforeach
            </nav>
        </div>
        <div class="border-t border-white/10 p-4">
            <a href="{{ route('perfil') }}" class="flex items-center gap-3 rounded-2xl bg-white/10 p-3 transition hover:bg-white/15">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#b27acc] to-[#512569] text-xs font-bold">AD</div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold leading-5">{{ $roleLabels[$currentRole] ?? 'Usuario' }}</p>
                    <p class="truncate text-[11px] leading-4 text-white/45">{{ $clinicName }}</p>
                </div>
                <span class="text-white/30">›</span>
            </a>
        </div>
    </aside>
    <div id="content-shell" class="h-[100dvh] min-w-0 flex-1 overflow-y-auto overscroll-y-contain">
        <header class="sticky top-0 z-20 flex h-20 shrink-0 items-center border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-7">
            <button id="sidebar-open" class="mr-3 rounded-xl border border-slate-200 p-2 text-slate-600 lg:hidden" aria-label="Abrir menú"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <div id="global-search" class="relative hidden max-w-md flex-1 sm:block">
                <div class="flex items-center gap-3 rounded-xl bg-slate-100 px-4 py-2.5 text-sm transition focus-within:bg-white focus-within:ring-2 focus-within:ring-[#9700cf]/20">
                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input id="global-search-input" type="search" autocomplete="off" placeholder="Buscar una sección..." class="min-w-0 flex-1 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400">
                </div>
                <div id="global-search-results" class="absolute left-0 right-0 top-[calc(100%+8px)] z-50 hidden overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                    <p class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Ir a una sección</p>
                    @foreach($navigation as $item)
                        <a href="{{ route($item['route']) }}" data-search-item data-search-text="{{ \Illuminate\Support\Str::lower($item['label'].' '.$item['route']) }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-[#f6e8ff] hover:text-[#9700cf]"><span>{{ $item['label'] }}</span><span class="text-slate-300">↗</span></a>
                    @endforeach
                    <a href="{{ route('perfil') }}" data-search-item data-search-text="perfil cuenta usuario configuración personal" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-[#f6e8ff] hover:text-[#9700cf]"><span>Mi perfil</span><span class="text-slate-300">↗</span></a>
                    <p id="global-search-empty" class="hidden px-3 py-5 text-center text-xs text-slate-400">No se encontró esa sección.</p>
                </div>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <span class="hidden text-right sm:block"><span class="block text-xs font-semibold text-slate-700">{{ now()->locale('es')->isoFormat('dddd, D [de] MMMM') }}</span><span class="block text-[10px] text-slate-400">{{ $currentRole === 'superadmin' ? 'Panel técnico' : ($currentRole === 'admin' ? 'Resumen' : ($currentRole === 'doctor' ? 'Área clínica' : 'Panel operativo')) }}</span></span>
                <details class="group relative ml-2">
                    <summary class="relative flex cursor-pointer list-none rounded-xl border border-slate-200 bg-white p-2.5 text-slate-500 transition hover:bg-slate-50 hover:text-[#9700cf]" aria-label="Notificaciones">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>
                        @if($roleNotifications->isNotEmpty())
                            <span class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-fuchsia-600 px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $roleNotifications->count() > 9 ? '9+' : $roleNotifications->count() }}</span>
                        @endif
                    </summary>
                    <div class="fixed left-1/2 top-[4.5rem] z-50 flex max-h-[calc(100dvh-5rem)] w-[calc(100vw-1rem)] max-w-[360px] -translate-x-1/2 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:left-auto sm:right-0 sm:top-[calc(100%+10px)] sm:max-h-[min(32rem,calc(100dvh-6rem))] sm:w-[min(360px,calc(100vw-2rem))] sm:max-w-none sm:translate-x-0">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <p class="font-bold text-slate-800">Notificaciones</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $currentRole === 'doctor' ? 'Agenda y seguimiento clínico' : ($currentRole === 'asistente' ? 'Citas e inventario' : 'Avisos del sistema') }}</p>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
                            @forelse($roleNotifications as $notification)
                                <a href="{{ $notification['url'] }}" class="flex gap-3 rounded-xl p-3 transition {{ $notification['type'] === 'appointment_highlight' ? 'border border-amber-200 bg-amber-50 hover:bg-amber-100' : 'hover:bg-[#fbf0ff]' }}">
                                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $notification['type'] === 'appointment_highlight' ? 'bg-amber-100 text-amber-700' : 'bg-[#f6e8ff] text-[#9700cf]' }}">
                                        @if($notification['type'] === 'appointment_highlight')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.2 5.7 6.1.3-4.7 3.9 1.6 5.9-5.2-3.3-5.2 3.3 1.6-5.9L3.7 9l6.1-.3L12 3Z"/></svg>
                                        @elseif($notification['type'] === 'inventory')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7"/></svg>
                                        @elseif($notification['type'] === 'treatment')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Z"/></svg>
                                        @else
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2ZM7 2v4m10-4v4M3 9h18"/></svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <strong class="block truncate text-sm text-slate-700">{{ $notification['title'] }}</strong>
                                        <span class="mt-1 block text-xs text-slate-400">{{ $notification['detail'] }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="px-5 py-10 text-center">
                                    <p class="text-sm font-semibold text-slate-600">Todo al día</p>
                                    <p class="mt-1 text-xs text-slate-400">No tienes avisos pendientes.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </details>
                <details class="group relative ml-1">
                    <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl bg-[#ead2fb] text-xs font-bold text-[#9700cf] transition hover:bg-[#d3a2f2]" aria-label="Abrir menú de cuenta">
                        {{ mb_strtoupper(mb_substr(session('access_name', $roleLabels[$currentRole] ?? 'U'), 0, 1)) }}{{ collect(explode(' ', session('access_name', '')))->filter()->skip(1)->take(1)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}
                    </summary>
                    <div class="absolute right-0 top-[calc(100%+10px)] z-50 w-[min(320px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-[#ead2fb] bg-white shadow-2xl">
                        <div class="bg-gradient-to-br from-[#fbf0ff] to-white px-5 py-5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#ead2fb] text-sm font-bold text-[#9700cf]">
                                    {{ mb_strtoupper(mb_substr(session('access_name', $roleLabels[$currentRole] ?? 'U'), 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-800">{{ session('access_name', $roleLabels[$currentRole] ?? 'Usuario') }}</p>
                                    <p class="mt-0.5 text-xs font-semibold text-[#9700cf]">{{ $roleLabels[$currentRole] ?? 'Usuario' }}</p>
                                </div>
                            </div>
                            <div class="mt-4 rounded-xl border border-[#ead2fb] bg-white/80 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Correo de acceso</p>
                                <p class="mt-1 truncate text-xs font-semibold text-slate-700">{{ session('access_email', 'Sin correo registrado') }}</p>
                            </div>
                        </div>
                        <div class="space-y-1 border-t border-slate-100 p-2">
                            <a href="{{ route('perfil') }}" class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-[#fbf0ff] hover:text-[#9700cf]"><span>Ver mi perfil</span><span>&rarr;</span></a>
                            @if($currentRole !== 'asistente')
                                <a href="{{ route('perfil.security') }}" class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-[#fbf0ff] hover:text-[#9700cf]"><span>Seguridad y contraseña</span><span>&rarr;</span></a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 pt-1">
                                @csrf
                                <button type="submit" class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-fuchsia-600 transition hover:bg-fuchsia-50"><span>Cerrar sesión</span><span>&rarr;</span></button>
                            </form>
                        </div>
                    </div>
                </details>
                <form method="POST" action="{{ route('logout') }}" class="ml-1">
                    @csrf
                    <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-[#9700cf]" aria-label="Cerrar sesión" title="Cerrar sesión">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 17l5-5-5-5m5 5H3m11-9h6v18h-6"/></svg>
                    </button>
                </form>
            </div>
        </header>
        <main id="main-content" class="min-w-0 overflow-x-hidden p-3 sm:p-7 xl:p-9"><div class="mx-auto min-w-0 max-w-[1500px]">
            <div id="df-refresh-content">@yield('content')</div>
            @include('partials.refresh-loading')
        </div></main>
    </div>
</div>
@stack('scripts')
</body>
</html>







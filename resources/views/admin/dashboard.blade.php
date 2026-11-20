@extends('layouts.app')
@section('title', 'Centro de superadministración')
@section('content')
@php
    $healthy = $system['database'] === 'Conectada' && !$settings['maintenance'];
    $roleLabels = ['superadmin' => 'Superadministrador', 'admin' => 'Administrador operativo', 'doctor' => 'Doctor', 'asistente' => 'Asistente', 'pendiente' => 'Pendiente'];
@endphp

<div class="space-y-6">
    @if(session('access_denied'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">{{ session('access_denied') }}</div>
    @endif

    <section class="overflow-hidden rounded-3xl p-6 text-white shadow-[0_22px_70px_rgba(33,21,46,.28)] ring-1 ring-[#512569] sm:p-8" style="background: linear-gradient(135deg, #21152e 0%, #512569 100%);">
        <div>
            <div>
                <h1 class="text-3xl font-bold sm:text-4xl">Control del sistema</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-white/80">Gestiona cuentas, seguridad, configuración y servicios técnicos. Este perfil conserva las funciones del administrador técnico anterior.</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-[1.2fr_.8fr]">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_6px_24px_rgba(30,34,60,.06)]">
            <div><h2 class="font-bold text-slate-950">Acciones administrativas</h2><p class="mt-1 text-xs text-slate-500">Herramientas frecuentes para gestionar el sistema.</p></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach([
                    [route('admin.system'), 'Sistema', 'Diagnóstico y respaldo'],
                    [route('admin.preferences'), 'Preferencias', 'Preferencias globales'],
                ] as [$url, $title, $detail])
                    <a href="{{ $url }}" class="group rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-[#d3a2f2] hover:bg-[#fbf0ff]">
                        <span class="flex items-center justify-between text-sm font-bold text-slate-800">{{ $title }} <span class="text-[#9700cf] transition group-hover:translate-x-1">&rarr;</span></span>
                        <span class="mt-1 block text-xs text-slate-500">{{ $detail }}</span>
                    </a>
                @endforeach
            </div>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_6px_24px_rgba(30,34,60,.06)]">
            <h2 class="font-bold text-slate-950">Control y seguridad</h2>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-violet-50 p-4"><p class="text-2xl font-bold text-violet-700">{{ $operations['auditsToday'] }}</p><p class="mt-1 text-xs font-semibold text-slate-600">Eventos hoy</p></div>
                <div class="rounded-xl bg-fuchsia-50 p-4"><p class="text-2xl font-bold text-fuchsia-700">{{ $operations['admins'] }}</p><p class="mt-1 text-xs font-semibold text-slate-600">Superadministradores</p></div>
                <div class="col-span-2 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="text-xs font-semibold text-slate-600">Preferencias esencial</span><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $operations['configurationReady'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $operations['configurationReady'] ? 'Completa' : 'Revisar' }}</span></div>
            </div>
        </article>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['Cuentas registradas', $accounts['total'], 'Usuarios del sistema', '#9700cf'],
            ['Administradores', $accounts['admins'], 'Operación clínica integral', '#0f766e'],
            ['Doctores', $accounts['doctors'], 'Acceso clinico', '#7c25ff'],
            ['Asistentes', $accounts['assistants'], 'Acceso operativo', '#a126bb'],
            ['Cuentas pendientes', $accounts['pending'], 'Requieren revision', $accounts['pending'] ? '#d97706' : '#059669'],
        ] as [$label, $value, $detail, $color])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)]" style="border-top: 4px solid {{ $color }};">
                <p class="text-3xl font-bold text-slate-900">{{ $value }}</p>
                <p class="mt-2 text-sm font-bold text-slate-700">{{ $label }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $detail }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 class="font-bold text-slate-900">Servicios del sistema</h2><p class="mt-1 text-xs text-slate-400">Estado del entorno y sus dependencias</p></div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-700">Supervision activa</span>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                @foreach([
                    ['Base de datos', $system['database'], $system['databaseDriver'].' · '.$system['databaseSize'], $system['database'] === 'Conectada'],
                    ['Correo', $system['mailDriver'] === 'LOG' ? 'Modo local' : 'Configurado', 'Controlador '.$system['mailDriver'], $system['mailDriver'] !== 'LOG'],
                    ['Cache', 'Disponible', 'Controlador '.$system['cacheDriver'], true],
                    ['Aplicación', 'Versión estable', 'Laravel '.$system['laravelVersion'].' · PHP '.$system['phpVersion'], true],
                ] as [$name, $status, $detail, $ok])
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between"><p class="text-sm font-bold text-slate-700">{{ $name }}</p><span class="h-2.5 w-2.5 rounded-full {{ $ok ? 'bg-emerald-500' : 'bg-amber-500' }}"></span></div>
                        <p class="mt-3 text-sm font-semibold {{ $ok ? 'text-emerald-700' : 'text-amber-700' }}">{{ $status }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $detail }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="flex items-center justify-between"><div><h2 class="font-bold text-slate-900">Modulos y proteccion</h2><p class="mt-1 text-xs text-slate-400">Preferencias globales activas</p></div><a href="{{ route('admin.preferences') }}" class="text-xs font-bold text-[#9700cf]">Configurar</a></div>
            <div class="mt-5 space-y-3">
                @foreach([
                    ['Modo mantenimiento', $settings['maintenance']],
                    ['Auditoria administrativa', $settings['audit']],
                    ['Recordatorios automaticos', $settings['reminders']],
                    ['Alertas de inventario', $settings['inventoryAlerts']],
                ] as [$label, $enabled])
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                        <span class="text-sm font-semibold text-slate-600">{{ $label }}</span>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' }}">{{ $enabled ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Cuentas recientes</h2><p class="mt-1 text-xs text-slate-400">Últimas cuentas actualizadas</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($recentUsers as $user)
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f6e8ff] text-xs font-bold text-[#9700cf]">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-700">{{ $user->name }}</p><p class="truncate text-xs text-slate-400">{{ $user->email }}</p></div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $roleLabels[$user->role] ?? $user->role }}</span>
                    </div>
                @empty
                    <p class="p-8 text-center text-sm text-slate-400">No hay cuentas registradas.</p>
                @endforelse
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Auditoria reciente</h2><p class="mt-1 text-xs text-slate-400">Cambios administrativos del sistema</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($recentAudits as $audit)
                    <div class="flex gap-3 px-5 py-3.5">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-[#9700cf]"></span>
                        <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-700">{{ str_replace('_', ' ', ucfirst($audit->action)) }}</p><p class="mt-1 text-xs text-slate-400">{{ $audit->actor_name ?: 'Sistema' }} · {{ $audit->created_at->diffForHumans() }}</p></div>
                    </div>
                @empty
                    <div class="p-8 text-center"><p class="text-sm font-semibold text-slate-500">Sin eventos recientes</p><p class="mt-1 text-xs text-slate-400">Los cambios administrativos apareceran aqui.</p></div>
                @endforelse
            </div>
        </article>
    </section>
</div>
@endsection

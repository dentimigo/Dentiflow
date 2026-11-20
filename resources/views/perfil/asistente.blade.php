@extends('layouts.app')

@section('title','Perfil de asistente')

@section('content')
@php
    $initials = collect(explode(' ', $usuario['nombre']))->filter()->map(fn ($item) => mb_substr($item, 0, 1))->take(2)->join('');
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-4xl font-bold tracking-tight text-slate-900">Perfil</h1>
            <p class="mt-2 text-sm text-slate-500">Información de cuenta, contacto y seguridad del perfil.</p>
        </div>
        <a href="{{ route('perfil.edit') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#9700cf] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">Editar perfil</a>
    </section>

    @include('partials.flash-success')

    <section class="grid items-start gap-6 lg:grid-cols-3">
        <aside>
            <article class="flex flex-col rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                <div class="flex flex-col items-center text-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-3xl bg-[#ead2fb] text-3xl font-bold text-[#9700cf]">{{ strtoupper($initials) }}</div>
                    <span class="mt-4 rounded-full bg-violet-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-700">Área operativa</span>
                    <h2 class="mt-5 text-xl font-bold text-slate-900">{{ $usuario['nombre'] }}</h2>
                </div>

                <div class="mt-7 space-y-3">
                    <div class="rounded-2xl bg-[#fbf0ff] p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Correo</p>
                        <p class="mt-2 break-words text-sm font-semibold text-slate-800">{{ $usuario['email'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#fbf0ff] p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Teléfono</p>
                        <a href="tel:{{ preg_replace('/\D+/', '', $usuario['telefono']) }}" class="mt-2 inline-flex text-sm font-semibold text-[#9700cf] hover:text-[#512569]">{{ $usuario['telefono'] }}</a>
                    </div>
                    <div class="rounded-2xl bg-[#fbf0ff] p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Puesto</p>
                        <p class="mt-2 text-sm font-semibold text-slate-800">{{ $usuario['especialidad'] }}</p>
                    </div>
                </div>
            </article>
        </aside>

        <article class="flex flex-col rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                    <h2 class="text-xl font-bold text-slate-900">Información del perfil</h2>
                    <p class="mt-1 text-sm text-slate-500">Datos principales para identificar la cuenta dentro del sistema.</p>
                    <div class="mt-5 grid flex-1 content-start gap-3">
                        <div class="rounded-2xl bg-[#fbf0ff] p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Rol</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ session('access_role') === 'admin' ? 'Administrador' : 'Asistente' }}</p>
                        </div>
                        <div class="rounded-2xl bg-[#fbf0ff] p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Último acceso</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ $lastAccess }}</p>
                        </div>
                    </div>
        </article>

        <article class="flex flex-col rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                    <h2 class="text-xl font-bold text-slate-900">Seguridad</h2>
                    <div class="mt-5 space-y-3">
                        <a href="{{ route('perfil.security') }}" class="flex items-center justify-between rounded-2xl bg-[#fbf0ff] p-4 transition hover:bg-[#ead2fb]"><span class="text-sm font-semibold text-slate-700">Cambiar contraseña</span><span class="text-[#9700cf]">&rarr;</span></a>
                        <div class="rounded-2xl bg-[#fbf0ff] p-4"><p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Estado</p><p class="mt-2 text-sm font-semibold text-slate-800">Cuenta activa</p></div>
                    </div>
        </article>
    </section>
    @if ($billingWorkspace)
        @include('perfil.suscripcion', ['workspace' => $billingWorkspace])
    @endif
</div>
@endsection


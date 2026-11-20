@extends('layouts.app')
@section('title', 'Perfil administrativo')
@section('content')
@php
    $initials = collect(explode(' ', $usuario->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('');
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Perfil administrativo</h1>
            <p class="mt-2 text-sm text-slate-500">Información de acceso y seguridad de tu cuenta técnica.</p>
        </div>
        <a href="{{ route('perfil.edit') }}" class="inline-flex justify-center rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]">Editar perfil</a>
    </div>

    @include('partials.flash-success')

    <div class="grid gap-5 lg:grid-cols-[.85fr_1.5fr]">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-[0_4px_20px_rgba(30,34,60,.04)]">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-3xl bg-[#ead2fb] text-2xl font-bold text-[#9700cf]">{{ mb_strtoupper($initials) }}</div>
            <h2 class="mt-5 text-xl font-bold">{{ $usuario->name }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ $usuario->email }}</p>
            <span class="mt-4 inline-flex rounded-full bg-[#f6e8ff] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-[#9700cf]">Administrador técnico</span>
            <div class="mt-6 border-t border-slate-100 pt-5 text-left">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Identificador</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">ADM-{{ str_pad($usuario->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
        </article>

        <div class="space-y-5">
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
                <h2 class="font-bold">Información de la cuenta</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nombre</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ $usuario->name }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Correo</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ $usuario->email }}</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Rol</p><p class="mt-2 text-sm font-semibold text-slate-700">Administrador de aplicación</p></div>
                    <div class="rounded-xl bg-slate-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Estado</p><p class="mt-2 flex items-center gap-2 text-sm font-semibold text-[#9700cf]"><span class="h-2 w-2 rounded-full bg-[#9700cf]"></span>Activo</p></div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
                <div class="flex items-center justify-between"><div><h2 class="font-bold">Seguridad</h2><p class="mt-1 text-xs text-slate-400">Protección de la cuenta administrativa</p></div><span class="rounded-full bg-[#f6e8ff] px-3 py-1 text-[10px] font-bold text-[#9700cf]">PROTEGIDA</span></div>
                <div class="mt-5 flex items-center justify-between rounded-xl border border-slate-100 p-4"><div><p class="text-sm font-semibold text-slate-700">Contraseña</p><p class="text-xs text-slate-400">Actualizada recientemente</p></div><a href="{{ route('perfil.security') }}" class="text-xs font-bold text-[#9700cf] transition hover:text-[#512569]">Cambiar</a></div>
            </article>
        </div>
    </div>
</div>
@endsection





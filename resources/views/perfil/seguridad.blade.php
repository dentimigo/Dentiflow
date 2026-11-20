@extends('layouts.app')

@section('title', 'Seguridad de la cuenta')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-4xl font-bold text-slate-900">Cambiar contrase&ntilde;a</h1>
            <p class="mt-2 text-sm text-slate-500">Actualiza tu contrase&ntilde;a directamente desde tu sesi&oacute;n activa.</p>
        </div>
        <a href="{{ route('perfil') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600">&larr; Volver al perfil</a>
    </header>

    @include('partials.flash-success')
    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
    @endif

    <div>
        <section class="rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#ead2fb] text-[#9700cf]">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </span>
            <h2 class="mt-4 text-xl font-bold text-slate-800">Nueva contrase&ntilde;a</h2>
            <p class="mt-2 text-sm text-slate-500">Por seguridad, confirma tu contrase&ntilde;a actual y escribe la nueva.</p>
            <form method="POST" action="{{ route('perfil.password.update') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block text-sm font-semibold text-slate-600">Contrase&ntilde;a actual
                    <input type="password" name="current_password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-[#9700cf]">
                </label>
                <label class="block text-sm font-semibold text-slate-600">Nueva contrase&ntilde;a
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-[#9700cf]">
                </label>
                <label class="block text-sm font-semibold text-slate-600">Confirmar nueva contrase&ntilde;a
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-[#9700cf]">
                </label>
                <button class="w-full rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Actualizar contrase&ntilde;a</button>
            </form>
        </section>
    </div>
</div>
@endsection


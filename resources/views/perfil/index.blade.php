@extends('layouts.app')

@section('title','Perfil de doctor')

@section('content')
@php
    $initials = collect(explode(' ', $usuario['nombre']))->filter()->map(fn ($item) => mb_substr($item, 0, 1))->take(2)->join('');
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-4xl font-bold tracking-tight text-slate-900">Atención clínica</h1>
            <p class="mt-2 text-sm text-slate-500">Información profesional, agenda y seguridad de la cuenta.</p>
        </div>
        <a href="{{ route('perfil.edit') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#9700cf] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">Editar perfil</a>
    </section>

    @include('partials.flash-success')

    <section class="grid items-start gap-6 lg:grid-cols-3">
        <aside>
            <article class="flex flex-col rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                <div class="flex flex-col items-center text-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-3xl bg-[#ead2fb] text-3xl font-bold text-[#9700cf]">{{ strtoupper($initials) }}</div>
                    <span class="mt-4 rounded-full bg-violet-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-violet-700">Área clínica</span>
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
                        <p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Especialidad</p>
                        <p class="mt-2 text-sm font-semibold text-slate-800">{{ $usuario['especialidad'] }}</p>
                    </div>
                </div>
            </article>
        </aside>

        <article class="flex flex-col rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Agenda semanal</h2>
                            <p class="mt-1 text-sm text-slate-500">Tu espacio de trabajo clínico y disponibilidad habitual.</p>
                        </div>
                        <button type="button" onclick="document.getElementById('schedule-editor').showModal()" class="rounded-xl bg-[#9700cf] px-4 py-2 text-xs font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">Editar horario</button>
                    </div>
                    <div class="mt-5 flex-1 space-y-2">
                        @foreach($agenda as $item)
                            <div class="flex items-center justify-between rounded-2xl bg-[#fbf0ff] px-4 py-3">
                                <span class="text-sm font-bold text-[#512569]">{{ $item['dia'] }}</span>
                                <span class="text-xs font-semibold {{ $item['enabled'] ? 'text-slate-600' : 'text-slate-300' }}">{{ $item['horario'] }}</span>
                            </div>
                        @endforeach
                    </div>
        </article>

        <article class="flex flex-col rounded-3xl border border-[#ead2fb] bg-white p-6 shadow-[0_18px_60px_rgba(81,37,105,.08)]">
                    <h2 class="text-xl font-bold text-slate-900">Seguridad</h2>
                    <div class="mt-5 space-y-3">
                        <a href="{{ route('perfil.security') }}" class="flex items-center justify-between rounded-2xl bg-[#fbf0ff] p-4 transition hover:bg-[#ead2fb]"><span class="text-sm font-semibold text-slate-700">Cambiar contraseña</span><span class="text-[#9700cf]">&rarr;</span></a>
                        <div class="rounded-2xl bg-[#fbf0ff] p-4"><p class="text-xs font-bold uppercase tracking-wider text-[#ad79d3]">Último acceso</p><p class="mt-2 text-sm font-semibold text-slate-800">{{ $lastAccess }}</p></div>
                    </div>
        </article>
    </section>

    <dialog id="schedule-editor" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form method="POST" action="{{ route('perfil.schedule.update') }}" class="bg-white">
            @csrf
            <div class="flex items-start justify-between border-b border-slate-100 p-6">
                <div><h2 class="text-xl font-bold text-slate-800">Editar agenda semanal</h2><p class="mt-1 text-sm text-slate-400">Elige tus días y horas disponibles.</p></div>
                <button type="button" onclick="document.getElementById('schedule-editor').close()" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">x</button>
            </div>
            @if($errors->any())<div class="mx-6 mt-5 rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-3 text-sm text-fuchsia-700">{{ $errors->first() }}</div>@endif
            <div class="max-h-[60vh] space-y-3 overflow-y-auto p-6">
                @foreach($agenda as $item)
                    <div class="grid items-center gap-3 rounded-2xl border border-slate-200 p-4 sm:grid-cols-[90px_1fr_1fr]">
                        <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
                            <input type="hidden" name="schedule[{{ $item['weekday'] }}][enabled]" value="0">
                            <input type="checkbox" name="schedule[{{ $item['weekday'] }}][enabled]" value="1" @checked($item['enabled']) class="h-4 w-4 rounded border-slate-300 text-[#9700cf] focus:ring-[#9700cf]">
                            {{ $item['dia'] }}
                        </label>
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Entrada
                            <input type="time" name="schedule[{{ $item['weekday'] }}][start_time]" value="{{ $item['start_time'] }}" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        </label>
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Salida
                            <input type="time" name="schedule[{{ $item['weekday'] }}][end_time]" value="{{ $item['end_time'] }}" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 p-5">
                <button type="button" onclick="document.getElementById('schedule-editor').close()" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600">Cancelar</button>
                <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]">Guardar agenda</button>
            </div>
        </form>
    </dialog>
</div>
@endsection




@extends('layouts.app')
@section('title', 'Preferencias')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <h1 class="text-3xl font-bold tracking-tight">Preferencias</h1>
        <p class="mt-2 text-sm text-slate-500">Configura accesos, operación diaria y reglas generales de la clínica.</p>
    </div>

    @include('partials.flash-success')
    @if($errors->any())
        <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-medium text-fuchsia-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.preferences.update') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
        @csrf

        <section>
            <h2 class="font-bold">Información general</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Nombre de la clínica
                    <input name="clinic_name" value="{{ old('clinic_name', $settings->clinic_name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                </label>
                <label class="text-sm font-semibold text-slate-700">Correo de soporte
                    <input type="email" name="support_email" value="{{ old('support_email', $settings->support_email) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                </label>
            </div>
        </section>

        <section class="mt-8 border-t border-slate-100 pt-6">
            <h2 class="font-bold">Agenda y operación</h2>
            <p class="mt-1 text-xs text-slate-400">Reglas base para la programación diaria del consultorio.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Hora de apertura
                    <input type="time" name="opening_time" value="{{ old('opening_time', substr($settings->opening_time, 0, 5)) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                </label>
                <label class="text-sm font-semibold text-slate-700">Hora de cierre
                    <input type="time" name="closing_time" value="{{ old('closing_time', substr($settings->closing_time, 0, 5)) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                </label>
                <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Estado inicial de nuevas citas
                    <select name="default_appointment_status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                        @foreach(['Pendiente', 'Confirmada'] as $status)
                            <option value="{{ $status }}" @selected(old('default_appointment_status', $settings->default_appointment_status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section id="modules" class="mt-8 border-t border-slate-100 pt-6">
            <h2 class="font-bold">Modulos y preferencias</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach([
                    ['Recordatorios automaticos', 'Enviar avisos de citas al personal', $settings->automatic_reminders_enabled],
                    ['Alertas de inventario', 'Notificar cuando un material llegue al minimo', $settings->inventory_alerts_enabled],
                    ['Modo de mantenimiento', 'Bloquear temporalmente el acceso operativo', $settings->maintenance_mode_enabled],
                    ['Auditoria administrativa', 'Registrar cambios relevantes de usuarios y configuracion', $settings->administrative_audit_enabled],
                ] as [$title, $detail, $checked])
                    <label class="flex cursor-pointer items-center gap-4 py-4">
                        <span class="flex-1"><strong class="block text-sm text-slate-700">{{ $title }}</strong><span class="text-xs text-slate-400">{{ $detail }}</span></span>
                        <input type="checkbox" name="modules[]" value="{{ $loop->index }}" @checked($checked) class="h-5 w-5 rounded border-slate-300 text-[#9700cf] focus:ring-[#9700cf]">
                    </label>
                @endforeach
            </div>
        </section>

        <section class="mt-8 border-t border-slate-100 pt-6">
            <h2 class="font-bold">Permisos de asistentes</h2>
            <p class="mt-1 text-xs text-slate-400">Elige las secciones que el personal asistente puede ver y abrir.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @php($allowedAssistantSections = old('assistant_sections', $settings->assistantAllowedSections()))
                @foreach($assistantSectionOptions as $key => $option)
                    @php($isRequiredAssistantSection = $key === 'inicio')
                    @if($isRequiredAssistantSection)
                        <input type="hidden" name="assistant_sections[]" value="{{ $key }}">
                    @endif
                    <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-[#d3a2f2] hover:bg-[#fbf0ff]">
                        <input type="checkbox" name="assistant_sections[]" value="{{ $key }}" @checked($isRequiredAssistantSection || in_array($key, $allowedAssistantSections, true)) @disabled($isRequiredAssistantSection) class="mt-1 h-5 w-5 rounded border-slate-300 text-[#9700cf] focus:ring-[#9700cf] disabled:opacity-60">
                        <span>
                            <strong class="block text-sm text-slate-700">{{ $option['label'] }} @if($isRequiredAssistantSection)<span class="ml-2 text-[10px] font-bold uppercase tracking-wider text-[#9700cf]">Base</span>@endif</strong>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">{{ $option['detail'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <div class="mt-6 flex justify-end">
            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection





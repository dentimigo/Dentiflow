@extends('layouts.app')

@section('title', 'Expediente clínico')

@section('content')
@php
    $availableTabs = [
        'resumen' => 'Resumen',
        'consultas' => 'Consultas',
        'antecedentes' => 'Antecedentes',
        'citas' => 'Citas',
    ];
    $tab = array_key_exists(request('tab', 'resumen'), $availableTabs) ? request('tab', 'resumen') : 'resumen';
    $isDoctor = in_array(session('access_role'), ['doctor', 'admin'], true);
    $isOperationalAdmin = session('access_role') === 'admin';
    $medical = $patient->medicalHistory;
    $emergency = is_array($patient->contacto_emergencia) ? $patient->contacto_emergencia : [];
    $fieldClass = 'mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/15 disabled:bg-slate-50 disabled:text-slate-400';
@endphp

<div class="space-y-6">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex items-center gap-4">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[#ead2fb] text-xl font-bold text-[#9700cf]">
                {{ mb_strtoupper(mb_substr($patient->nombre, 0, 1)) }}
            </span>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $patient->nombre }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $patient->email ?: 'Sin correo' }} @if($patient->telefono) · <a href="tel:{{ preg_replace('/\D+/', '', $patient->telefono) }}" class="font-semibold text-[#9700cf] hover:text-[#512569]">{{ $patient->telefono }}</a>@else · Sin teléfono @endif</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('pacientes.edit', ['paciente' => $patient, 'from' => 'expedientes']) }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">Editar paciente</a>
            <a href="{{ route('expedientes') }}" class="rounded-xl bg-slate-800 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-950">Volver</a>
        </div>
    </header>

    @include('partials.flash-success')
    @if($errors->any())
        <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">{{ $errors->first() }}</div>
    @endif

    <nav class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5">
        @foreach($availableTabs as $key => $label)
            <a href="{{ route('pacientes.expediente', ['paciente' => $patient, 'tab' => $key]) }}" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ $tab === $key ? 'bg-[#9700cf] text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($tab === 'resumen')
        <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Alertas médicas</p>
                <p class="mt-3 text-sm font-semibold {{ $medical?->allergies ? 'text-fuchsia-600' : 'text-[#9700cf]' }}">{{ $medical?->allergies ?: 'Sin alergias registradas' }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $medical?->conditions ?: 'Sin padecimientos registrados' }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Próxima cita</p>
                @if($nextAppointment)
                    <p class="mt-3 text-xl font-bold text-slate-900">{{ $nextAppointment->date->format('d/m/Y') }}</p>
                    <p class="text-xs text-slate-500">{{ date('H:i', strtotime($nextAppointment->time)) }} · {{ $nextAppointment->tipo }}</p>
                @else
                    <p class="mt-3 text-sm text-slate-400">Sin cita programada</p>
                @endif
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="font-bold text-slate-900">Datos personales</h2>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-400">Correo</dt><dd class="font-semibold">{{ $patient->email ?: 'No registrado' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Teléfono</dt><dd class="font-semibold">@if($patient->telefono)<a href="tel:{{ preg_replace('/\D+/', '', $patient->telefono) }}" class="text-[#9700cf] hover:text-[#512569]">{{ $patient->telefono }}</a>@else No registrado @endif</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-slate-400">Dirección</dt><dd class="font-semibold">{{ $patient->direccion ?: 'No registrada' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Seguro</dt><dd class="font-semibold">{{ $patient->seguro ?: 'Sin seguro' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Póliza</dt><dd class="font-semibold">{{ $patient->poliza ?: 'No registrada' }}</dd></div>
                </dl>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="font-bold text-slate-900">Contacto de emergencia</h2>
                <div class="mt-5 rounded-xl bg-slate-50 p-4">
                    <p class="font-semibold">{{ $emergency['nombre'] ?? 'No registrado' }}</p>
                    @if(!empty($emergency['relacion']))<p class="text-sm text-slate-500">{{ $emergency['relacion'] }}</p>@endif
                    @if(!empty($emergency['telefono']))<a href="tel:{{ preg_replace('/\D+/', '', $emergency['telefono']) }}" class="mt-2 inline-flex text-sm font-semibold text-[#9700cf] hover:text-[#512569]">{{ $emergency['telefono'] }}</a>@endif
                </div>
                <div class="mt-5 flex justify-between text-xs text-slate-400">
                    <span>Consultas clínicas</span>
                    <strong class="text-slate-700">{{ $patient->clinicalRecords->count() }}</strong>
                </div>
            </article>
        </section>
    @elseif($tab === 'consultas')
        <div class="space-y-5">
            @if($isDoctor)
                <form method="POST" action="{{ route('pacientes.clinical.store', $patient) }}" class="rounded-2xl border border-slate-200 bg-white p-6">
                    @csrf
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-bold text-slate-900">Nueva nota clínica</h2>
                            <p class="mt-1 text-xs text-slate-400">Registra la consulta del paciente.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        @if($isOperationalAdmin)
                            <label class="block text-xs font-bold text-slate-700 lg:col-span-2">Doctor responsable
                                <select name="dentist_id" required class="{{ $fieldClass }}">
                                    <option value="">Selecciona un doctor</option>
                                    @foreach($dentists as $dentist)<option value="{{ $dentist->id }}" @selected((string) old('dentist_id') === (string) $dentist->id)>{{ $dentist->nombre }}</option>@endforeach
                                </select>
                            </label>
                        @endif
                        <label class="block text-xs font-bold text-slate-700">Fecha y hora
                            <input type="datetime-local" name="recorded_at" value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}" required class="{{ $fieldClass }}">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Motivo
                            <input name="reason" value="{{ old('reason') }}" required class="{{ $fieldClass }}">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Diagnóstico
                            <textarea name="diagnosis" rows="2" class="{{ $fieldClass }}">{{ old('diagnosis') }}</textarea>
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Procedimiento
                            <textarea name="procedure" rows="2" class="{{ $fieldClass }}">{{ old('procedure') }}</textarea>
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Receta e indicaciones
                            <textarea name="prescription" rows="2" class="{{ $fieldClass }}">{{ old('prescription') }}</textarea>
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Próxima revisión
                            <input type="date" name="next_visit" value="{{ old('next_visit') }}" class="{{ $fieldClass }}">
                        </label>
                        <label class="block text-xs font-bold text-slate-700 lg:col-span-2">Notas
                            <textarea name="notes" rows="2" class="{{ $fieldClass }}">{{ old('notes') }}</textarea>
                        </label>
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <a href="{{ route('pacientes.expediente', ['paciente' => $patient, 'tab' => 'consultas']) }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">Cancelar</a>
                        <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Aplicar cambios</button>
                    </div>
                </form>
            @endif

            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-slate-900">Consultas clínicas</h2>
                        <p class="mt-1 text-xs text-slate-400">Notas registradas del paciente.</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-[#9700cf]">{{ $patient->clinicalRecords->count() }}</span>
                </div>
                @forelse($patient->clinicalRecords as $record)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-bold text-[#9700cf]">{{ $record->recorded_at->format('d/m/Y · H:i') }}</p>
                                <h3 class="mt-1 font-bold text-slate-900">{{ $record->reason }}</h3>
                            </div>
                            <span class="text-xs text-slate-400">{{ $record->dentist?->nombre ?: 'Sin doctor asignado' }}</span>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @if($record->diagnosis)<div><b class="text-[10px] uppercase text-slate-400">Diagnóstico</b><p class="text-sm">{{ $record->diagnosis }}</p></div>@endif
                            @if($record->procedure)<div><b class="text-[10px] uppercase text-slate-400">Procedimiento</b><p class="text-sm">{{ $record->procedure }}</p></div>@endif
                            @if($record->prescription)<div class="sm:col-span-2 rounded-xl bg-[#f6e8ff] p-3 text-sm text-[#512569]">{{ $record->prescription }}</div>@endif
                            @if($record->notes)<div class="sm:col-span-2 text-sm text-slate-500">{{ $record->notes }}</div>@endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-sm text-slate-400">Sin consultas clínicas.</div>
                @endforelse
            </section>
        </div>
    @elseif($tab === 'antecedentes')
        <form method="POST" action="{{ route('pacientes.medical.update', $patient) }}" class="rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Antecedentes médicos</h2>
                    <p class="mt-1 text-xs text-slate-400">Información importante antes de cualquier procedimiento.</p>
                </div>
                @if(!$isDoctor)<span class="text-xs font-semibold text-slate-400">Solo lectura</span>@endif
            </div>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="text-xs font-bold text-slate-700">Tipo de sangre
                    <input name="blood_type" value="{{ old('blood_type', $medical?->blood_type) }}" @disabled(!$isDoctor) class="{{ $fieldClass }}">
                </label>
                @foreach(['allergies' => 'Alergias', 'conditions' => 'Padecimientos', 'medications' => 'Medicamentos actuales', 'surgeries' => 'Cirugías previas', 'notes' => 'Observaciones'] as $name => $label)
                    <label class="text-xs font-bold text-slate-700 {{ $name === 'notes' ? 'md:col-span-2' : '' }}">{{ $label }}
                        <textarea name="{{ $name }}" rows="{{ $name === 'notes' ? 4 : 3 }}" @disabled(!$isDoctor) class="{{ $fieldClass }}">{{ old($name, $medical?->{$name}) }}</textarea>
                    </label>
                @endforeach
            </div>
            @if($isDoctor)
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('pacientes.expediente', ['paciente' => $patient, 'tab' => 'antecedentes']) }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">Cancelar</a>
                    <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Aplicar cambios</button>
                </div>
            @endif
        </form>
    @else
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 p-5">
                <h2 class="font-bold text-slate-900">Historial de citas</h2>
                <p class="mt-1 text-xs text-slate-400">Selecciona una cita para consultar todo lo realizado ese d&iacute;a.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($patient->appointments as $appointment)
                    <div class="grid gap-4 p-5 sm:grid-cols-[70px_1fr_auto] sm:items-center">
                        <div class="w-fit rounded-xl bg-slate-50 px-3 py-2 text-center">
                            <b class="block text-slate-900">{{ $appointment->date->format('d') }}</b>
                            <small class="block text-slate-500">{{ $appointment->date->locale('es')->isoFormat('MMM') }}</small>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $appointment->tipo }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ date('H:i', strtotime($appointment->time)) }} · {{ $appointment->dentist?->nombre ?: 'Sin doctor asignado' }}</p>
                        </div>
                        <span class="text-xs font-semibold text-slate-500">{{ $appointment->estado }}</span>
                    </div>
                    @php($completedTreatment = $appointment->treatments->firstWhere('estado', 'Realizado') ?? $appointment->treatments->first())
                    <details class="border-t border-slate-50 bg-slate-50/50 px-5 py-3 sm:pl-[140px]">
                        <summary class="cursor-pointer list-none text-sm font-bold text-[#9700cf]">Ver detalle de la consulta <span class="ml-1">&darr;</span></summary>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5">
                            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Procedimiento principal</dt><dd class="mt-1 font-semibold text-slate-700">{{ $appointment->tipo }}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Descuento</dt><dd class="mt-1 font-semibold text-slate-700">{{ number_format((float) $appointment->descuento, 2) }}%</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Costo adicional</dt><dd class="mt-1 font-semibold text-slate-700">${{ number_format((float) $appointment->costo_extra, 2) }}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Procedimiento extra</dt><dd class="mt-1 font-semibold text-slate-700">{{ $appointment->procedimiento_extra ?: 'Ninguno' }}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Descripci&oacute;n del descuento</dt><dd class="mt-1 whitespace-pre-line font-medium text-slate-700">{{ $appointment->completion_description ?: 'Sin descripci&oacute;n del descuento' }}</dd></div>
                                <div><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Costo final</dt><dd class="mt-1 font-bold text-emerald-700">{{ $completedTreatment ? '$'.number_format((float) $completedTreatment->costo, 2) : 'Pendiente' }}</dd></div>
                            </dl>
                        </div>
                    </details>
                @empty
                    <div class="p-12 text-center text-sm text-slate-400">Sin citas.</div>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection


@extends('layouts.app')

@section('title','Crear Cita')

@section('content')
@php
    $fieldClass = fn (string $field) => 'mt-2 w-full rounded-2xl border px-4 py-3 focus:outline-none focus:ring-2 ' . ($errors->has($field) ? 'border-red-400 bg-red-50/40 focus:ring-red-200' : 'border-gray-300 focus:ring-purple-300');
    $fieldDot = fn (string $field) => $errors->has($field) ? '<span data-error-for="'.$field.'" class="ml-2 inline-block h-2.5 w-2.5 rounded-full bg-red-500 align-middle" title="'.$errors->first($field).'"></span>' : '';
    $customTreatmentOption = '__custom_treatment__';
    $quickPatientOption = '__quick_patient__';
@endphp

<div>

    <div class="flex justify-between items-center mb-8">

        <div>

            <h1 class="text-4xl font-bold text-gray-800">
                Agendar nueva cita
            </h1>
            <p class="mt-2 text-gray-500">Complete el formulario para registrar una cita.</p>

        </div>

        <a href="{{ route('citas') }}" class="bg-gray-200 text-gray-800 px-6 py-3 rounded-2xl hover:bg-gray-300">
            Volver
        </a>

    </div>

    <div class="bg-white rounded-3xl shadow-lg p-8">

        @if ($errors->any())
            <div id="appointment-errors" class="mb-6 rounded-3xl bg-fuchsia-50 border border-fuchsia-200 p-4 text-fuchsia-700 transition duration-300">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('citas.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <label class="block">
                    <span class="text-gray-700">Paciente {!! $fieldDot('paciente') !!}</span>
                    <select id="appointment-patient" name="paciente" class="{{ $fieldClass('paciente') }}">
                        <option value="">Seleccione un paciente</option>
                        @foreach($pacientes as $paciente)
                            <option value="{{ $paciente }}" {{ old('paciente') === $paciente ? 'selected' : '' }}>{{ $paciente }}</option>
                        @endforeach
                        <option value="{{ $quickPatientOption }}" @selected(old('paciente') === $quickPatientOption)>+ Nuevo paciente rapido</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-gray-700">Dentista {!! $fieldDot('dentista') !!}</span>
                    @if($currentDentist)
                        <div class="mt-2 flex w-full items-center gap-3 rounded-2xl border border-[#d3a2f2] bg-[#f6e8ff] px-4 py-3 text-[#512569]">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#ead2fb] text-xs font-bold">{{ mb_strtoupper(mb_substr($currentDentist->nombre, 0, 1)) }}</span>
                            <span class="font-semibold">{{ $currentDentist->nombre }}</span>
                            <span class="ml-auto text-xs text-[#ad79d3]">Sesión actual</span>
                        </div>
                    @else
                        <select name="dentista" class="{{ $fieldClass('dentista') }}">
                            <option value="">Seleccione un dentista</option>
                            @foreach($dentistas as $dentista)
                                <option value="{{ $dentista }}" {{ old('dentista') === $dentista ? 'selected' : '' }}>{{ $dentista }}</option>
                            @endforeach
                        </select>
                    @endif
                </label>
            </div>

            <div class="grid gap-6 md:grid-cols-4">
                <label class="block">
                    <span class="text-gray-700">Fecha {!! $fieldDot('fecha') !!}</span>
                    <input id="appointment-date" type="date" name="fecha" value="{{ old('fecha', $defaultDate) }}" min="{{ $defaultDate }}" class="{{ $fieldClass('fecha') }}">
                </label>

                <label class="block">
                    <span class="text-gray-700">Hora de inicio {!! $fieldDot('hora') !!}</span>
                    <input id="appointment-start-time" type="time" name="hora" value="{{ old('hora', $defaultStartTime) }}" step="60" required data-current-time="{{ $defaultStartTime }}" class="{{ $fieldClass('hora') }}">
                </label>

                <label class="block">
                    <span class="text-gray-700">Hora de terminado {!! $fieldDot('hora_fin') !!}</span>
                    <input id="appointment-end-time" type="time" name="hora_fin" value="{{ old('hora_fin', $defaultEndTime) }}" step="60" required class="{{ $fieldClass('hora_fin') }}">
                </label>

                <label class="block">
                    <span class="text-gray-700">Tratamiento {!! $fieldDot('tipo') !!}</span>
                    <select id="appointment-treatment" name="tipo" required class="{{ $fieldClass('tipo') }}">
                        <option value="">Seleccione un tratamiento</option>
                        @foreach($tratamientos as $tratamiento)
                            <option value="{{ $tratamiento->tratamiento }}" @selected(old('tipo') === $tratamiento->tratamiento)>
                                {{ $tratamiento->tratamiento }} · {{ $tratamiento->tipo }} · ${{ number_format($tratamiento->costo, 2) }}
                            </option>
                        @endforeach
                        <option value="{{ $customTreatmentOption }}" @selected(old('tipo') === $customTreatmentOption)>Emergencia / otro tratamiento</option>
                    </select>
                    @if($tratamientos->isEmpty())
                        <p class="mt-2 text-xs text-violet-600">No hay tratamientos registrados en el catálogo.</p>
                    @endif
                </label>
            </div>

            <section id="quick-patient-wrapper" class="hidden rounded-2xl border border-[#d3a2f2] bg-[#fbf0ff] p-5">
                <div>
                    <p class="text-sm font-bold text-[#512569]">Nuevo paciente rapido</p>
                    <p class="mt-1 text-xs text-[#7a6984]">Crea al paciente y agenda la cita ahora. El nombre y el numero de emergencia son obligatorios; podras completar el resto del expediente despues.</p>
                </div>
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700">Nombre completo {!! $fieldDot('nuevo_paciente_nombre') !!}</span>
                        <input id="quick-patient-name" type="text" name="nuevo_paciente_nombre" value="{{ old('nuevo_paciente_nombre') }}" maxlength="255" placeholder="Nombre y apellidos" class="{{ $fieldClass('nuevo_paciente_nombre') }}">
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700">Numero de emergencia {!! $fieldDot('nuevo_paciente_telefono') !!}</span>
                        <input id="quick-patient-phone" type="tel" name="nuevo_paciente_telefono" value="{{ old('nuevo_paciente_telefono') }}" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" placeholder="2221234567" class="{{ $fieldClass('nuevo_paciente_telefono') }}">
                        <span class="mt-2 block text-xs text-slate-500">Debe contener exactamente 10 digitos.</span>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-teal-200 bg-teal-50/60 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-teal-800">Cita en colaboración</p>
                        <p class="mt-1 text-xs leading-5 text-teal-700">Opcional. La cita se guardará en ambas agendas y solo se permitirá si los dos doctores tienen disponible todo el horario.</p>
                    </div>
                    <label class="block w-full lg:max-w-md">
                        <span class="text-sm font-semibold text-slate-700">Doctor colaborador {!! $fieldDot('collaborator_dentist_id') !!}</span>
                        <select id="collaborator-dentist" name="collaborator_dentist_id" class="{{ $fieldClass('collaborator_dentist_id') }}">
                            <option value="">Sin colaboración</option>
                            @foreach($collaboratorDentists as $dentist)
                                <option value="{{ $dentist->id }}" @selected((string) old('collaborator_dentist_id') === (string) $dentist->id)>
                                    {{ $dentist->nombre }}{{ $dentist->especialidad ? ' · '.$dentist->especialidad : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <div id="emergency-treatment-wrapper" class="hidden grid gap-5 md:grid-cols-[1fr_220px]">
                <label>
                    <span class="text-gray-700">Tratamiento de emergencia {!! $fieldDot('tratamiento_emergencia') !!}</span>
                    <input type="text" name="tratamiento_emergencia" value="{{ old('tratamiento_emergencia') }}" maxlength="255" placeholder="Ej. Dolor agudo, fractura dental, valoración urgente" class="{{ $fieldClass('tratamiento_emergencia') }}">
                    <span class="mt-2 block text-xs text-slate-500">Se agregará al catálogo como emergencia para poder guardar la cita.</span>
                </label>
                <label>
                    <span class="text-gray-700">Precio opcional {!! $fieldDot('costo_emergencia') !!}</span>
                    <input type="number" name="costo_emergencia" value="{{ old('costo_emergencia') }}" min="0" step="0.01" placeholder="0.00" class="{{ $fieldClass('costo_emergencia') }}">
                    <span class="mt-2 block text-xs text-slate-500">Déjalo vacío si no aplica.</span>
                </label>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-[#9700cf] text-white px-8 py-3 rounded-2xl hover:bg-[#512569]">
                    Guardar cita
                </button>
            </div>

        </form>

    </div>

</div>

<script>
    (() => {
        const dateInput = document.getElementById('appointment-date');
        const startInput = document.getElementById('appointment-start-time');
        const endInput = document.getElementById('appointment-end-time');
        const treatmentSelect = document.getElementById('appointment-treatment');
        const patientSelect = document.getElementById('appointment-patient');
        const quickPatientWrapper = document.getElementById('quick-patient-wrapper');
        const quickPatientName = document.getElementById('quick-patient-name');
        const quickPatientPhone = document.getElementById('quick-patient-phone');
        const emergencyWrapper = document.getElementById('emergency-treatment-wrapper');
        const errorBox = document.getElementById('appointment-errors');
        const duration = Number(@json($appointmentDuration));
        const today = @json($defaultDate);
        const customTreatmentOption = @json($customTreatmentOption);
        const quickPatientOption = @json($quickPatientOption);
        const pad = value => String(value).padStart(2, '0');
        const currentTime = () => {
            const now = new Date();
            return `${pad(now.getHours())}:${pad(now.getMinutes())}`;
        };
        const addMinutes = (time, minutes) => {
            const [hours, mins] = time.split(':').map(Number);
            const date = new Date();
            date.setHours(hours, mins + minutes, 0, 0);
            return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };
        const syncTimeLimits = () => {
            if (!dateInput || !startInput) return;
            if (dateInput.value === today) {
                const minTime = currentTime();
                startInput.min = minTime;
                if (startInput.value < minTime) {
                    startInput.value = minTime;
                    if (endInput) endInput.value = addMinutes(minTime, duration);
                }
            } else {
                startInput.removeAttribute('min');
            }
        };

        dateInput?.addEventListener('change', syncTimeLimits);
        startInput?.addEventListener('change', () => {
            if (endInput && startInput.value) {
                endInput.value = addMinutes(startInput.value, duration);
            }
        });
        const syncEmergencyTreatment = () => {
            emergencyWrapper?.classList.toggle('hidden', treatmentSelect?.value !== customTreatmentOption);
        };
        treatmentSelect?.addEventListener('change', syncEmergencyTreatment);
        const syncQuickPatient = () => {
            const isQuick = patientSelect?.value === quickPatientOption;
            quickPatientWrapper?.classList.toggle('hidden', !isQuick);
            if (quickPatientName) quickPatientName.required = isQuick;
            if (quickPatientPhone) quickPatientPhone.required = isQuick;
            if (isQuick) quickPatientName?.focus();
        };
        patientSelect?.addEventListener('change', syncQuickPatient);
        syncTimeLimits();
        syncEmergencyTreatment();
        syncQuickPatient();
        setInterval(syncTimeLimits, 60000);

        if (errorBox) {
            setTimeout(() => {
                errorBox.classList.add('opacity-0');
                setTimeout(() => errorBox.remove(), 300);
            }, 5000);
        }
    })();
</script>

@endsection




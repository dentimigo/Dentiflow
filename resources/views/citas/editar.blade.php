@extends('layouts.app')

@section('title','Editar Cita')

@section('content')
@php
    $fieldClass = fn (string $field) => 'mt-2 w-full rounded-2xl border px-4 py-3 focus:outline-none focus:ring-2 ' . ($errors->has($field) ? 'border-red-400 bg-red-50/40 focus:ring-red-200' : 'border-gray-300 focus:ring-purple-300');
    $fieldDot = fn (string $field) => $errors->has($field) ? '<span data-error-for="'.$field.'" class="ml-2 inline-block h-2.5 w-2.5 rounded-full bg-red-500 align-middle" title="'.$errors->first($field).'"></span>' : '';
    $catalogCostMap = $catalogTreatments->mapWithKeys(fn ($treatment) => [$treatment->tratamiento => (float) $treatment->costo]);
    $storedExtraNames = $cita['procedimiento_extra'] ? explode(' + ', $cita['procedimiento_extra']) : [''];
    $extraNames = old('procedimiento_extra', $storedExtraNames);
    $extraNames = is_array($extraNames) ? array_values($extraNames) : [$extraNames];
    $storedExtraCosts = [];
    if ($cita['procedimiento_extra']) {
        $remainingExtraCost = (float) $cita['costo_extra'];
        foreach ($storedExtraNames as $index => $name) {
            $catalogCost = (float) ($catalogCostMap[$name] ?? 0);
            $storedExtraCosts[$index] = max(0, $remainingExtraCost - $catalogCost);
            $remainingExtraCost = 0;
        }
    }
    $extraCosts = old('costo_extra', $storedExtraCosts ?: [0]);
    $extraCosts = is_array($extraCosts) ? array_values($extraCosts) : [$extraCosts];
    $extraRows = collect($extraNames)->map(fn ($name, $index) => [
        'name' => $name,
        'cost' => $extraCosts[$index] ?? ($index === 0 ? $cita['costo_extra'] : 0),
    ])->values();
    $extraRows = $extraRows->isEmpty() ? collect([['name' => '', 'cost' => 0]]) : $extraRows;
@endphp

<div>

    <div class="flex justify-between items-center mb-8">

        <div>

            <h1 class="text-4xl font-bold text-gray-800">
                Editar cita
            </h1>
            <p class="mt-2 text-gray-500">Ajusta los detalles de la cita y guarda los cambios.</p>

        </div>

        <a href="{{ $backUrl }}" class="bg-gray-200 text-gray-800 px-6 py-3 rounded-2xl hover:bg-gray-300">
            &larr; Volver
        </a>

    </div>

    <div class="bg-white rounded-3xl shadow-lg p-8">

        @if(session('warning'))
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-3xl bg-fuchsia-50 border border-fuchsia-200 p-4 text-fuchsia-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('citas.update', ['id' => $cita['id']]) }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="return_to" value="{{ $backUrl }}">

            <div class="grid gap-6 md:grid-cols-2">
                <label class="block">
                    <span class="text-gray-700">Paciente {!! $fieldDot('paciente') !!}</span>
                    <select name="paciente" class="{{ $fieldClass('paciente') }}">
                        <option value="">Seleccione un paciente</option>
                        @foreach($pacientes as $paciente)
                            <option value="{{ $paciente }}" {{ old('paciente', $cita['paciente']) === $paciente ? 'selected' : '' }}>{{ $paciente }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-gray-700">Dentista {!! $fieldDot('dentista') !!}</span>
                    @if($currentDentist)
                        <div class="mt-2 flex w-full items-center gap-3 rounded-2xl border border-[#d3a2f2] bg-[#f6e8ff] px-4 py-3 text-[#512569]">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#ead2fb] text-xs font-bold">{{ mb_strtoupper(mb_substr($currentDentist->nombre, 0, 1)) }}</span>
                            <span class="font-semibold">{{ $currentDentist->nombre }}</span>
                            <span class="ml-auto text-xs text-[#ad79d3]">Doctor de la sesi&oacute;n</span>
                        </div>
                    @else
                        <select name="dentista" class="{{ $fieldClass('dentista') }}">
                            <option value="">Seleccione un dentista</option>
                            @foreach($dentistas as $dentista)
                                <option value="{{ $dentista }}" {{ old('dentista', $cita['dentista']) === $dentista ? 'selected' : '' }}>{{ $dentista }}</option>
                            @endforeach
                        </select>
                    @endif
                </label>
            </div>

            <div class="grid gap-6 md:grid-cols-4">
                <label class="block">
                    <span class="text-gray-700">Fecha {!! $fieldDot('fecha') !!}</span>
                    <input type="date" name="fecha" value="{{ old('fecha', date('Y-m-d', strtotime($cita['fecha']))) }}" class="{{ $fieldClass('fecha') }}">
                </label>

                <label class="block">
                    <span class="text-gray-700">Hora de inicio {!! $fieldDot('hora') !!}</span>
                    <input type="time" name="hora" value="{{ old('hora', $cita['hora_inicio']) }}" step="60" required class="{{ $fieldClass('hora') }}">
                </label>

                <label class="block">
                    <span class="text-gray-700">Hora de terminado {!! $fieldDot('hora_fin') !!}</span>
                    <input type="time" name="hora_fin" value="{{ old('hora_fin', $cita['hora_fin']) }}" step="60" required class="{{ $fieldClass('hora_fin') }}">
                </label>

                <label class="block">
                    <span class="text-gray-700">Tipo de servicio {!! $fieldDot('tipo') !!}</span>
                    <span class="relative mt-2 block">
                        <span class="flex w-full items-center overflow-hidden rounded-2xl border bg-white focus-within:ring-2 {{ $errors->has('tipo') ? 'border-red-400 bg-red-50/40 focus-within:ring-red-200' : 'border-gray-300 focus-within:ring-purple-300' }}">
                            <input id="appointment-service" type="text" name="tipo" value="{{ old('tipo', $cita['tipo']) }}" placeholder="Escribe para buscar un tratamiento" autocomplete="off" class="min-w-0 flex-1 bg-transparent py-3 pl-4 pr-2 outline-none">
                            <span id="appointment-service-cost" class="pointer-events-none shrink-0 border-l border-gray-200 px-4 py-1 text-sm font-normal text-slate-500">${{ number_format($baseCost, 2) }}</span>
                        </span>
                        <div id="service-dropdown" class="absolute left-0 right-0 top-[calc(100%+8px)] z-40 hidden max-h-72 overflow-y-auto rounded-2xl border border-[#ead2fb] bg-white p-2 shadow-2xl">
                            <span class="block px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-[#ad79d3]">Seleccionar tratamiento</span>
                            @foreach($catalogTreatments as $treatment)
                                <button type="button" data-treatment-option data-dropdown="service" data-value="{{ $treatment->tratamiento }}" data-search="{{ \Illuminate\Support\Str::lower($treatment->tratamiento.' '.$treatment->tipo) }}" class="flex w-full items-center justify-between gap-4 rounded-xl px-3 py-3 text-left transition hover:bg-[#f6e8ff]">
                                    <span><strong class="block text-sm text-slate-700">{{ $treatment->tratamiento }}</strong><small class="text-xs text-slate-400">{{ $treatment->tipo }}</small></span>
                                    <span class="shrink-0 text-sm font-bold text-[#9700cf]">${{ number_format($treatment->costo, 2) }}</span>
                                </button>
                            @endforeach
                            <span data-dropdown-empty="service" class="hidden px-3 py-6 text-center text-xs text-slate-400">No se encontr&oacute; el tratamiento.</span>
                        </div>
                    </span>
                </label>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <label class="block">
                    <span class="text-gray-700">Estado {!! $fieldDot('estado') !!}</span>
                    <select name="estado" class="{{ $fieldClass('estado') }}">
                        @foreach(['Pendiente', 'Confirmada', 'Cancelada', 'Asistida', 'Terminada'] as $estado)
                            <option value="{{ $estado }}" {{ old('estado', $cita['estado']) === $estado ? 'selected' : '' }}>{{ $estado }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="block">
                    <span class="text-gray-700">ID de cita</span>
                    <div class="mt-2 rounded-2xl border border-gray-300 bg-gray-50 px-4 py-3 text-gray-700">{{ $cita['id'] }}</div>
                </div>
            </div>

            <section class="rounded-2xl border border-teal-200 bg-teal-50/60 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-teal-800">Cita en colaboración</p>
                        <p class="mt-1 text-xs leading-5 text-teal-700">La cita aparecerá en ambas agendas. Al guardar se comprobará la disponibilidad de los dos doctores.</p>
                    </div>
                    <label class="block w-full lg:max-w-md">
                        <span class="text-sm font-semibold text-slate-700">Doctor colaborador {!! $fieldDot('collaborator_dentist_id') !!}</span>
                        <select name="collaborator_dentist_id" class="{{ $fieldClass('collaborator_dentist_id') }}">
                            <option value="">Sin colaboración</option>
                            @foreach($collaboratorDentists as $dentist)
                                <option value="{{ $dentist->id }}" @selected((string) old('collaborator_dentist_id', $selectedCollaboratorId) === (string) $dentist->id)>
                                    {{ $dentist->nombre }}{{ $dentist->especialidad ? ' · '.$dentist->especialidad : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="rounded-3xl border border-emerald-200 bg-emerald-50/50 p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Ajuste económico</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Costos de la cita</h2>
                    </div>
                    <p class="text-2xl font-bold text-emerald-700">Total: $<span id="edit-total">0.00</span></p>
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl bg-white p-4">
                        <span class="text-xs text-slate-400">Costo base</span>
                        <p class="mt-1 font-bold text-slate-800">$<span id="edit-base">{{ number_format($baseCost, 2) }}</span></p>
                    </div>
                    <label class="block rounded-2xl bg-white p-4">
                        <span class="text-xs text-slate-400">Descuento (%) {!! $fieldDot('descuento') !!}</span>
                        <span class="mt-2 flex overflow-hidden rounded-xl border bg-white focus-within:ring-2 {{ $errors->has('descuento') ? 'border-red-400 bg-red-50/40 focus-within:ring-red-200' : 'border-slate-200 focus-within:ring-emerald-200' }}">
                            <input id="edit-discount" type="number" name="descuento" min="0" max="100" step="0.01" value="{{ old('descuento', $cita['descuento']) }}" class="min-w-0 flex-1 px-3 py-2 text-sm outline-none">
                            <span class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-400">%</span>
                        </span>
                    </label>
                </div>
                <div class="mt-4 space-y-3" id="extra-treatment-rows">
                    @foreach($extraRows as $row)
                        <div class="extra-treatment-row grid gap-4 rounded-2xl bg-white p-4 lg:grid-cols-[1fr_1.45fr_1fr_auto] lg:items-end">
                            <div>
                                <span class="text-xs text-slate-400">Costo base del extra</span>
                                <p class="mt-3 font-bold text-slate-800" data-extra-base-display>------</p>
                            </div>
                            <label class="block">
                                <span class="text-xs text-slate-400">Procedimiento extra {!! $fieldDot('procedimiento_extra') !!}</span>
                                <span class="relative mt-2 block">
                                    <input type="text" name="procedimiento_extra[]" maxlength="255" value="{{ $row['name'] }}" placeholder="Buscar o escribir libremente" autocomplete="off" data-extra-treatment class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-200">
                                    <div data-extra-dropdown class="absolute left-0 right-0 top-[calc(100%+8px)] z-[80] hidden max-h-56 overflow-y-auto rounded-2xl border border-[#ead2fb] bg-white p-2 shadow-2xl">
                                        <span class="block px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-[#ad79d3]">Elegir tratamiento</span>
                                        @foreach($catalogTreatments as $treatment)
                                            <button type="button" data-extra-option data-value="{{ $treatment->tratamiento }}" data-cost="{{ (float) $treatment->costo }}" data-search="{{ \Illuminate\Support\Str::lower($treatment->tratamiento.' '.$treatment->tipo) }}" class="flex w-full items-center justify-between gap-4 rounded-xl px-3 py-3 text-left transition hover:bg-[#f6e8ff]">
                                                <span><strong class="block text-sm text-slate-700">{{ $treatment->tratamiento }}</strong><small class="text-xs text-slate-400">{{ $treatment->tipo }}</small></span>
                                                <span class="shrink-0 text-sm font-bold text-[#9700cf]">${{ number_format($treatment->costo, 2) }}</span>
                                            </button>
                                        @endforeach
                                        <span data-extra-empty class="hidden px-3 py-6 text-center text-xs text-slate-400">Puedes dejarlo como texto libre.</span>
                                    </div>
                                </span>
                            </label>
                            <label class="block">
                                <span class="text-xs text-slate-400">Costo adicional {!! $fieldDot('costo_extra') !!}</span>
                                <input type="number" name="costo_extra[]" min="0" step="0.01" value="{{ $row['cost'] }}" data-extra-cost class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-200">
                            </label>
                            <button type="button" data-remove-extra class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-50">Quitar</button>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex justify-end gap-4">
                <button type="button" id="add-extra-treatment" class="inline-flex items-center justify-center rounded-2xl border border-[#d3a2f2] bg-white px-6 py-3 text-[#9700cf] hover:bg-[#fbf0ff]">
                    Agregar otro tratamiento
                </button>
                <a href="{{ $backUrl }}" class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-6 py-3 text-gray-700 hover:bg-gray-100">
                    Cancelar
                </a>
                <button type="submit" class="bg-[#9700cf] text-white px-8 py-3 rounded-2xl hover:bg-[#512569]">
                    Guardar cambios
                </button>
            </div>
        </form>

    </div>

</div>

<script>
    (() => {
        const treatmentCosts = @json($catalogTreatments->mapWithKeys(fn ($treatment) => [$treatment->tratamiento => (float) $treatment->costo]));
        let base = {{ json_encode($baseCost) }};
        const service = document.getElementById('appointment-service');
        const serviceCost = document.getElementById('appointment-service-cost');
        const editBase = document.getElementById('edit-base');
        const editDiscount = document.getElementById('edit-discount');
        const editTotal = document.getElementById('edit-total');
        const extraRowsContainer = document.getElementById('extra-treatment-rows');
        const addExtraTreatment = document.getElementById('add-extra-treatment');
        const money = value => Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

        const selectedExtraBase = row => {
            const input = row.querySelector('[data-extra-treatment]');
            const value = input?.value || '';
            return Object.prototype.hasOwnProperty.call(treatmentCosts, value) ? Number(treatmentCosts[value]) : 0;
        };

        const updateExtraBase = row => {
            const baseDisplay = row.querySelector('[data-extra-base-display]');
            const baseCost = selectedExtraBase(row);
            if (baseDisplay) {
                baseDisplay.textContent = baseCost > 0 ? '$' + money(baseCost) : '------';
            }
        };

        const updateRemoveButtons = () => {
            const rows = [...extraRowsContainer.querySelectorAll('.extra-treatment-row')];
            rows.forEach(row => {
                const remove = row.querySelector('[data-remove-extra]');
                remove?.classList.toggle('hidden', rows.length === 1);
            });
        };

        const updateTotal = () => {
            const extraRows = [...extraRowsContainer.querySelectorAll('.extra-treatment-row')];
            const extra = extraRows.reduce((sum, row) => {
                const additional = Number(row.querySelector('[data-extra-cost]')?.value || 0);
                return sum + selectedExtraBase(row) + additional;
            }, 0);
            const discount = Math.min(100, Math.max(0, Number(editDiscount?.value || 0)));
            const total = Math.max(0, (base + extra) * (1 - discount / 100));
            if (editBase) editBase.textContent = money(base);
            if (editTotal) editTotal.textContent = money(total);
        };

        const updateService = () => {
            base = Number(treatmentCosts[service?.value] ?? 0);
            if (serviceCost) serviceCost.textContent = '$' + money(base);
            updateTotal();
        };

        const configureServiceDropdown = (input, name, onSelect) => {
            const dropdown = document.getElementById(name + '-dropdown');
            const options = [...document.querySelectorAll(`[data-treatment-option][data-dropdown="${name}"]`)];
            const empty = document.querySelector(`[data-dropdown-empty="${name}"]`);
            const open = (filter = false) => {
                const query = filter ? normalize(input.value) : '';
                let visible = 0;
                options.forEach(option => {
                    const matches = !query || normalize(option.dataset.search).includes(query);
                    option.classList.toggle('hidden', !matches);
                    if (matches) visible++;
                });
                empty?.classList.toggle('hidden', visible > 0);
                dropdown?.classList.remove('hidden');
            };

            input?.addEventListener('focus', () => open(false));
            input?.addEventListener('input', () => open(true));
            options.forEach(option => option.addEventListener('click', () => {
                input.value = option.dataset.value;
                dropdown?.classList.add('hidden');
                onSelect();
            }));
            document.addEventListener('click', event => {
                if (event.target !== input && !dropdown?.contains(event.target)) {
                    dropdown?.classList.add('hidden');
                }
            });
        };

        const configureExtraRow = row => {
            const input = row.querySelector('[data-extra-treatment]');
            const dropdown = row.querySelector('[data-extra-dropdown]');
            const options = [...row.querySelectorAll('[data-extra-option]')];
            const empty = row.querySelector('[data-extra-empty]');
            const costInput = row.querySelector('[data-extra-cost]');
            const remove = row.querySelector('[data-remove-extra]');

            const open = (filter = false) => {
                const query = filter ? normalize(input.value) : '';
                let visible = 0;
                options.forEach(option => {
                    const matches = !query || normalize(option.dataset.search).includes(query);
                    option.classList.toggle('hidden', !matches);
                    if (matches) visible++;
                });
                empty?.classList.toggle('hidden', visible > 0);
                dropdown?.classList.remove('hidden');
            };

            input?.addEventListener('focus', () => open(false));
            input?.addEventListener('input', () => {
                open(true);
                updateExtraBase(row);
                updateTotal();
            });
            options.forEach(option => option.addEventListener('click', () => {
                input.value = option.dataset.value;
                dropdown?.classList.add('hidden');
                updateExtraBase(row);
                updateTotal();
            }));
            costInput?.addEventListener('input', updateTotal);
            remove?.addEventListener('click', () => {
                row.remove();
                updateRemoveButtons();
                updateTotal();
            });
            document.addEventListener('click', event => {
                if (!row.contains(event.target)) {
                    dropdown?.classList.add('hidden');
                }
            });
            updateExtraBase(row);
        };

        addExtraTreatment?.addEventListener('click', () => {
            const source = extraRowsContainer.querySelector('.extra-treatment-row');
            if (!source) return;
            const row = source.cloneNode(true);
            row.querySelector('[data-extra-treatment]').value = '';
            row.querySelector('[data-extra-cost]').value = '0';
            row.querySelectorAll('[data-extra-dropdown], [data-extra-empty]').forEach(element => element.classList.add('hidden'));
            row.querySelectorAll('[data-extra-option]').forEach(element => element.classList.remove('hidden'));
            extraRowsContainer.appendChild(row);
            configureExtraRow(row);
            updateRemoveButtons();
            updateTotal();
            row.querySelector('[data-extra-treatment]')?.focus();
        });

        configureServiceDropdown(service, 'service', updateService);
        service?.addEventListener('input', updateService);
        [...extraRowsContainer.querySelectorAll('.extra-treatment-row')].forEach(configureExtraRow);
        editDiscount?.addEventListener('input', updateTotal);
        updateRemoveButtons();
        updateService();
    })();
</script>

@endsection








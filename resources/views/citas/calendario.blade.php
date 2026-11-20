@extends('layouts.app')

@section('title','Calendario')

@section('content')
@php
    $currentView = request('view', 'mes');
    $queryBase = request()->except(['date', 'year', 'month']);
    $statusStyles = [
        'Confirmada' => 'bg-[#ead2fb] text-[#8200b8]',
        'Pendiente' => 'bg-violet-100 text-violet-800',
        'Asistida' => 'bg-violet-100 text-violet-700',
        'Terminada' => 'bg-emerald-100 text-emerald-700',
        'Cancelada' => 'bg-fuchsia-100 text-fuchsia-700',
    ];
    $blockStyles = [
        'Descanso' => 'bg-amber-50 text-amber-700 border-amber-200',
        'Comida' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Ausencia' => 'bg-rose-50 text-rose-700 border-rose-200',
        'Vacaciones' => 'bg-sky-50 text-sky-700 border-sky-200',
        'Mantenimiento' => 'bg-slate-100 text-slate-700 border-slate-200',
        'Festivo' => 'bg-rose-50 text-rose-700 border-rose-200',
        'No disponible' => 'bg-slate-100 text-slate-700 border-slate-200',
    ];
    $navFor = fn ($view, $date) => $view === 'mes'
        ? array_merge($queryBase, ['view' => 'mes', 'year' => $date->year, 'month' => $date->month])
        : array_merge($queryBase, ['view' => $view, 'date' => $date->toDateString()]);
@endphp

<div class="space-y-6">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Calendario</h1>
            <p class="mt-2 text-sm text-slate-500">Organiza citas, horarios disponibles y d&iacute;as no disponibles.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <button type="button" onclick="document.getElementById('calendar-block-dialog')?.showModal()" class="inline-flex items-center justify-center rounded-xl border border-[#d3a2f2] bg-white px-5 py-3 text-sm font-bold text-[#9700cf]">Marcar d&iacute;a no disponible</button>
            <a href="{{ route('citas.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]"><span class="text-lg leading-none">+</span>Nueva cita</a>
        </div>
    </header>

    @include('partials.flash-success')
    @if($errors->any())
        <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">{{ $errors->first() }}</div>
    @endif

    @if($isDoctor || $isAssistant)
        <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Asistentes del consultorio</summary>
            @if($assistants->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Todavía no hay asistentes en este consultorio.</p>
            @else
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    @foreach($assistants as $assistant)
                        <li>{{ $assistant->name }}</li>
                    @endforeach
                </ul>
            @endif
        </details>
    @endif

    @if($isDoctor)
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-full rounded-xl bg-slate-100 p-1 sm:w-auto">
                    <a href="{{ route('calendario', array_merge(request()->except('agenda'), ['agenda' => 'mio'])) }}" class="flex-1 rounded-lg px-5 py-2.5 text-center text-sm font-bold transition sm:flex-none {{ $agendaMode === 'mio' ? 'bg-white text-[#9700cf] shadow-sm' : 'text-slate-500' }}">Mi agenda</a>
                    <a href="{{ route('calendario', array_merge(request()->except('agenda'), ['agenda' => 'equipo'])) }}" class="flex-1 rounded-lg px-5 py-2.5 text-center text-sm font-bold transition sm:flex-none {{ $agendaMode === 'equipo' ? 'bg-white text-[#9700cf] shadow-sm' : 'text-slate-500' }}">Agenda compartida</a>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-slate-500">
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-[#9700cf]"></span>Mis citas</span>
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-slate-400"></span>Otros doctores</span>
                    <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-teal-700"></span>Cita colaborativa</span>
                </div>
            </div>
            @if($agendaMode === 'equipo')
                <p class="mt-3 text-xs leading-5 text-slate-500">Puedes consultar los horarios del equipo. Las citas ajenas se muestran en gris y las citas donde participan dos doctores aparecen en verde azulado.</p>
            @endif
        </section>
    @endif

    <form method="GET" action="{{ route('calendario') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <input type="hidden" name="view" value="{{ $currentView }}">
        <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
        @if($isDoctor)<input type="hidden" name="agenda" value="{{ $agendaMode }}">@endif
        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
            @if($isAssistant)
                <label>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Doctor</span>
                    <select name="dentista" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <option value="Todos">Todos los doctores</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) $selectedDentist === (string) $dentist->id)>{{ $dentist->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tratamiento</span>
                <select name="tratamiento" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                    <option value="Todos">Todos los tratamientos</option>
                    @foreach($treatments as $treatment)
                        <option value="{{ $treatment }}" @selected($selectedTreatment === $treatment)>{{ $treatment }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Aplicar</button>
                <a href="{{ route('calendario', ['view' => $currentView, 'date' => $selectedDate->toDateString()]) }}" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600">Limpiar</a>
            </div>
        </div>
    </form>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg">
        <header class="border-b border-slate-100 p-4 sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('calendario', $navFor($currentView, $currentView === 'mes' ? $selectedDate->copy()->subMonth() : ($currentView === 'semana' ? $selectedDate->copy()->subWeek() : $selectedDate->copy()->subDay()))) }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500">&lsaquo;</a>
                    <a href="{{ route('calendario', array_merge($queryBase, ['view' => $currentView, 'date' => now()->toDateString(), 'year' => now()->year, 'month' => now()->month])) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600">Hoy</a>
                    <a href="{{ route('calendario', $navFor($currentView, $currentView === 'mes' ? $selectedDate->copy()->addMonth() : ($currentView === 'semana' ? $selectedDate->copy()->addWeek() : $selectedDate->copy()->addDay()))) }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500">&rsaquo;</a>
                    <h2 class="ml-2 text-base font-bold text-slate-800">{{ $currentView === 'mes' ? $selectedDate->locale('es')->isoFormat('MMMM [de] YYYY') : ($currentView === 'semana' ? $weekStart->locale('es')->isoFormat('D MMM').' - '.$weekEnd->locale('es')->isoFormat('D MMM YYYY') : $selectedDate->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }}</h2>
                </div>
                <div class="flex rounded-xl bg-slate-100 p-1">
                    @foreach(['dia' => 'D&iacute;a', 'semana' => 'Semana', 'mes' => 'Mes'] as $key => $label)
                        <a href="{{ route('calendario', $navFor($key, $selectedDate)) }}" class="flex-1 rounded-lg px-4 py-2 text-center text-xs font-bold transition sm:flex-none {{ $currentView === $key ? 'bg-white text-[#9700cf] shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">{!! $label !!}</a>
                    @endforeach
                </div>
            </div>
        </header>

        @if($currentView === 'dia')
            <div class="grid gap-4 p-4 lg:grid-cols-[1fr_300px]">
                <div class="overflow-hidden rounded-2xl border border-slate-100">
                    @foreach($timelineHours as $slot)
                        <div class="calendar-slot grid min-h-[96px] grid-cols-[72px_1fr] border-b border-slate-100 {{ $slot['free'] ? 'bg-emerald-50/20' : 'bg-white' }}" data-date="{{ $slot['date'] }}" data-time="{{ $slot['time'] }}" data-end-time="{{ $slot['end_time'] }}">
                            <div class="border-r border-slate-100 px-3 py-4 text-right text-xs font-bold text-slate-400">{{ $slot['time'] }}</div>
                            <div class="space-y-2 p-3">
                                @foreach($slot['blocks'] as $block)
                                    <div class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-xs font-semibold {{ $blockStyles[$block->type] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        <span>{{ $block->type }}@if($isOperationalAdmin) &middot; {{ $block->targetUser?->name ?? $block->dentist?->nombre }}@endif &middot; D&iacute;a completo</span>
                                        @if(!$isDoctor || $block->dentist_id === $doctorDentist?->id)<form method="POST" action="{{ route('calendario.bloqueos.destroy', $block) }}" onsubmit="return confirm('¿Volver a habilitar este día para nuevas citas?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-white/80 px-2.5 py-1 text-[10px] font-bold text-[#9700cf] shadow-sm transition hover:bg-white">Habilitar d&iacute;a</button>
                                        </form>@endif
                                    </div>
                                @endforeach
                                @foreach($slot['events'] as $event)
                                    @php
                                        $startsAt = \Illuminate\Support\Carbon::parse($event['fecha'].' '.$event['hora']);
                                        $minutesToStart = now()->diffInMinutes($startsAt, false);
                                        $near = $minutesToStart >= 0 && $minutesToStart <= 60;
                                        $samePatientDay = collect($todayEvents)->where('paciente', $event['paciente'])->count() > 1;
                                    @endphp
                                    <button type="button" onclick="document.getElementById('appointment-quick-{{ $event['id'] }}')?.showModal()" aria-label="Ver detalle de cita de {{ $event['paciente'] }}" class="draggable-event w-full cursor-grab rounded-xl p-3 text-left shadow-sm transition hover:shadow-md {{ $event['reminder_sent'] ? 'ring-2 ring-amber-300' : '' }}" style="border-left: 4px solid {{ $event['reminder_sent'] ? '#f59e0b' : ($event['color'] ?? '#9700cf') }}; background-color: {{ $event['reminder_sent'] ? '#fffbeb' : (($event['color'] ?? '#9700cf').'18') }};" draggable="true" data-detail-url="{{ route('citas.show', ['id' => $event['id']]) }}" data-event-id="{{ $event['id'] }}" data-current-date="{{ $event['fecha'] }}" data-time="{{ date('H:i', strtotime($event['hora'])) }}" data-end-time="{{ $event['hora_fin'] ? date('H:i', strtotime($event['hora_fin'])) : '' }}" data-patient="{{ $event['paciente'] }}" data-treatment="{{ $event['tipo'] }}" data-dentist="{{ $event['dentista'] }}" data-move-url="{{ route('citas.calendar.move', ['id' => $event['id']]) }}">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <strong class="text-xs" style="color: {{ $event['color'] ?? '#9700cf' }};">{{ $event['horario'] }}</strong>
                                            <span class="text-sm font-bold text-slate-800">{{ $event['paciente'] }}</span>
                                            <span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusStyles[$event['estado']] ?? 'bg-slate-100 text-slate-600' }}">{{ $event['estado'] }}</span>
                                            @if($near)<span class="rounded-full bg-amber-100 px-2 py-1 text-[9px] font-bold text-amber-700">Pronto</span>@endif
                                            @if($samePatientDay)<span class="rounded-full bg-fuchsia-100 px-2 py-1 text-[9px] font-bold text-fuchsia-700">Paciente repetido hoy</span>@endif
                                            @if($event['reminder_sent'])<span class="rounded-full bg-amber-100 px-2 py-1 text-[9px] font-bold text-amber-700">Destacada</span>@endif
                                        </span>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $event['tipo'] }} &middot; Doctor: {{ $event['dentista'] }}</span>
                                    </button>
                                @endforeach
                                @if($slot['free'])
                                    <a href="{{ $slot['create_url'] }}" class="block rounded-xl border border-dashed border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">Disponible</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <aside class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <h2 class="font-bold text-slate-900">Hoy cl&iacute;nico</h2>
                    <p class="text-xs text-slate-500">{{ $todayEvents->count() }} citas &middot; {{ $timelineHours->where('free', true)->count() }} bloques libres</p>
                    @forelse($todayEvents as $event)
                        <button type="button" onclick="document.getElementById('appointment-quick-{{ $event['id'] }}')?.showModal()" aria-label="Ver detalle de cita de {{ $event['paciente'] }}" class="w-full rounded-xl bg-white p-3 text-left text-xs shadow-sm">
                            <strong class="block text-slate-800">{{ $event['horario'] }} &middot; {{ $event['paciente'] }}</strong>
                            <span class="mt-1 block text-slate-500">{{ $event['tipo'] }}</span>
                        </button>
                    @empty
                        <p class="rounded-xl bg-white p-4 text-center text-xs text-slate-400">No hay citas este d&iacute;a.</p>
                    @endforelse
                </aside>
            </div>
        @endif

        @if($currentView === 'semana')
            <div class="overflow-x-auto p-4">
                <div class="grid min-w-[1000px] grid-cols-7 gap-3">
                    @foreach($weekDays as $day)
                        <div class="calendar-cell rounded-2xl border border-slate-100 bg-white p-3" data-date="{{ $day['date']->toDateString() }}" @if($day['available_slot']) data-time="{{ $day['available_start'] }}" data-end-time="{{ $day['available_end'] }}" @endif>
                            <div class="mb-3 text-center">
                                <p class="text-[10px] font-bold uppercase text-slate-400">{{ $day['date']->locale('es')->isoFormat('ddd') }}</p>
                                <p class="text-lg font-bold {{ $day['date']->isToday() ? 'text-[#9700cf]' : 'text-slate-800' }}">{{ $day['date']->format('d') }}</p>
                            </div>
                            <div class="space-y-2">
                                @foreach($day['blocks'] as $block)
                                    <div class="rounded-xl border px-3 py-2 text-[10px] font-semibold {{ $blockStyles[$block->type] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        <span class="block">{{ $block->type }}@if($isOperationalAdmin) &middot; {{ $block->targetUser?->name ?? $block->dentist?->nombre }}@endif &middot; D&iacute;a completo</span>
                                        @if(!$isDoctor || $block->dentist_id === $doctorDentist?->id)<form method="POST" action="{{ route('calendario.bloqueos.destroy', $block) }}" class="mt-2" onsubmit="return confirm('¿Volver a habilitar este día para nuevas citas?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full rounded-lg bg-white/80 px-2 py-1 text-[9px] font-bold text-[#9700cf] shadow-sm transition hover:bg-white">Habilitar d&iacute;a</button>
                                        </form>@endif
                                    </div>
                                @endforeach
                                @forelse($day['events'] as $event)
                                    <button type="button" onclick="document.getElementById('appointment-quick-{{ $event['id'] }}')?.showModal()" aria-label="Ver detalle de cita de {{ $event['paciente'] }}" class="draggable-event block w-full cursor-grab rounded-xl p-3 text-left shadow-sm {{ $event['reminder_sent'] ? 'ring-2 ring-amber-300' : '' }}" style="border: 2px solid {{ $event['reminder_sent'] ? '#f59e0b' : ($event['color'] ?? '#9700cf') }}; background-color: {{ $event['reminder_sent'] ? '#fffbeb' : (($event['color'] ?? '#9700cf').'14') }};" draggable="true" data-detail-url="{{ route('citas.show', ['id' => $event['id']]) }}" data-event-id="{{ $event['id'] }}" data-current-date="{{ $event['fecha'] }}" data-time="{{ date('H:i', strtotime($event['hora'])) }}" data-end-time="{{ $event['hora_fin'] ? date('H:i', strtotime($event['hora_fin'])) : '' }}" data-patient="{{ $event['paciente'] }}" data-treatment="{{ $event['tipo'] }}" data-dentist="{{ $event['dentista'] }}" data-move-url="{{ route('citas.calendar.move', ['id' => $event['id']]) }}">
                                        <strong class="block text-[11px]" style="color: {{ $event['color'] ?? '#9700cf' }};">{{ date('H:i', strtotime($event['hora'])) }}</strong>
                                        <span class="mt-1 block truncate text-xs font-bold text-slate-800">{{ $event['paciente'] }}</span>
                                        <span class="block truncate text-[10px] text-slate-500">{{ $event['tipo'] }}</span>
                                        <span class="block truncate text-[10px] text-slate-500">Doctor: {{ $event['dentista'] }}</span>
                                        @if($event['reminder_sent'])<span class="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-1 text-[9px] font-bold text-amber-700">Destacada</span>@endif
                                    </button>
                                @empty
                                    @if($day['available_slot'])
                                        <a href="{{ route('citas.create', ['fecha' => $day['date']->toDateString(), 'hora' => $day['available_start'], 'hora_fin' => \Illuminate\Support\Carbon::parse($day['date']->toDateString().' '.$day['available_start'])->addHour()->format('H:i')]) }}" class="block rounded-xl border border-dashed border-emerald-200 bg-emerald-50 p-3 text-center text-[10px] font-bold text-emerald-700">Disponible</a>
                                    @else
                                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-center text-[10px] font-bold text-slate-400">Sin horario</div>
                                    @endif
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($currentView === 'mes')
            <div class="overflow-x-auto p-4">
                <div class="grid min-w-[860px] grid-cols-7 border-b border-slate-100 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    @foreach(['Lun','Mar','Mie','Jue','Vie','Sab','Dom'] as $weekday)<div class="py-3">{{ $weekday }}</div>@endforeach
                </div>
                <div class="grid min-w-[860px] grid-cols-7">
                    @foreach($calendarDays as $cell)
                        @php
                            $dayBlocks = $calendarBlocks->filter(fn ($block) => $cell['date'] && $block->date->toDateString() === $cell['date']);
                        @endphp
                        <div class="calendar-cell min-h-36 border-b border-r border-slate-100 p-2 {{ !$cell['day'] ? 'bg-slate-50/60' : 'bg-white' }}" @if($cell['date']) data-date="{{ $cell['date'] }}" @endif>
                            @if($cell['day'])
                                <div class="mb-2 flex items-center justify-between"><span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $cell['date'] === now()->toDateString() ? 'bg-[#9700cf] text-white' : 'text-slate-600' }}">{{ $cell['day'] }}</span><span class="text-[9px] text-slate-300">{{ count($cell['events']) ?: '' }}</span></div>
                                <div class="space-y-1">
                                    @foreach($dayBlocks->take(1) as $block)
                                        <div class="rounded-md border px-2 py-1 text-[9px] font-semibold {{ $blockStyles[$block->type] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                            <span class="block truncate">{{ $block->type }}@if($isOperationalAdmin) &middot; {{ $block->targetUser?->name ?? $block->dentist?->nombre }}@endif</span>
                                            @if(!$isDoctor || $block->dentist_id === $doctorDentist?->id)<form method="POST" action="{{ route('calendario.bloqueos.destroy', $block) }}" class="mt-1" onsubmit="return confirm('¿Volver a habilitar este día para nuevas citas?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full rounded bg-white/80 px-1.5 py-1 text-[8px] font-bold text-[#9700cf] shadow-sm">Habilitar</button>
                                            </form>@endif
                                        </div>
                                    @endforeach
                                    @foreach(array_slice($cell['events'], 0, 3) as $event)
                                        <button type="button" onclick="document.getElementById('appointment-quick-{{ $event['id'] }}')?.showModal()" aria-label="Ver detalle de cita de {{ $event['paciente'] }}" class="draggable-event block w-full cursor-grab rounded-md px-2 py-1.5 text-left text-[10px] font-semibold {{ $event['reminder_sent'] ? 'ring-2 ring-amber-300' : '' }}" style="border-left: 3px solid {{ $event['reminder_sent'] ? '#f59e0b' : ($event['color'] ?? '#9700cf') }}; background-color: {{ $event['reminder_sent'] ? '#fffbeb' : (($event['color'] ?? '#9700cf').'24') }}; color: {{ $event['reminder_sent'] ? '#92400e' : ($event['color'] ?? '#9700cf') }};" draggable="true" data-detail-url="{{ route('citas.show', ['id' => $event['id']]) }}" data-event-id="{{ $event['id'] }}" data-current-date="{{ $event['fecha'] }}" data-time="{{ date('H:i', strtotime($event['hora'])) }}" data-end-time="{{ $event['hora_fin'] ? date('H:i', strtotime($event['hora_fin'])) : '' }}" data-patient="{{ $event['paciente'] }}" data-treatment="{{ $event['tipo'] }}" data-dentist="{{ $event['dentista'] }}" data-move-url="{{ route('citas.calendar.move', ['id' => $event['id']]) }}">
                                            <span class="block truncate"><span class="font-bold">{{ date('H:i', strtotime($event['hora'])) }}</span> {{ $event['paciente'] }}</span>
                                            <span class="block truncate">Dr: {{ $event['dentista'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-lg">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#9700cf]">Agenda del mes</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">Citas por estado</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $monthAppointmentsCount }} citas programadas.</p>
                </div>
            </div>
            <div class="mt-5 space-y-4">
                @forelse($statusChart as $item)
                    @php($percent = $monthAppointmentsCount > 0 ? round(($item['value'] / $monthAppointmentsCount) * 100) : 0)
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-semibold text-slate-700">{{ $item['label'] }}</span>
                            <span class="text-xs font-bold text-slate-400">{{ $item['value'] }} &middot; {{ $percent }}%</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#9700cf]" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl bg-slate-50 p-5 text-center text-sm font-semibold text-slate-400">Sin citas para graficar.</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-lg">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#9700cf]">Tratamientos</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">M&aacute;s agendados</h2>
                    <p class="mt-1 text-sm text-slate-500">Principales tratamientos del mes visible.</p>
                </div>
            </div>
            <div class="mt-5 space-y-4">
                @forelse($topTreatmentChart as $item)
                    @php($percent = $monthAppointmentsCount > 0 ? max(6, round(($item['value'] / $monthAppointmentsCount) * 100)) : 0)
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-semibold text-slate-700">{{ $item['label'] }}</span>
                            <span class="shrink-0 text-xs font-bold text-slate-400">{{ $item['value'] }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-400" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl bg-slate-50 p-5 text-center text-sm font-semibold text-slate-400">Sin tratamientos para graficar.</p>
                @endforelse
            </div>
        </article>
    </section>
    @foreach($citas as $event)
        <dialog id="appointment-quick-{{ $event['id'] }}" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/50">
            <div class="bg-white">
                <div class="border-b border-slate-100 px-6 py-5">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#9700cf]">Detalle r&aacute;pido</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-900">{{ $event['paciente'] }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $event['tipo'] }}</p>
                </div>
                <div class="space-y-3 px-6 py-5 text-sm">
                    <p><strong>Horario:</strong> {{ date('d/m/Y', strtotime($event['fecha'])) }} &middot; {{ $event['horario'] }}</p>
                    <p><strong>Doctor:</strong> {{ $event['dentista'] }}</p>
                    <p><strong>Estado:</strong> {{ $event['estado'] }}</p>
                    <p><strong>Seguimiento:</strong> {{ $event['reminder_sent'] ? 'Destacada en notificaciones' : 'Sin resaltar' }}</p>
                </div>
                <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    @if(empty($event['other_doctor']))
                        <form method="POST" action="{{ route('citas.recordatorio', ['id' => $event['id']]) }}">@csrf<button class="rounded-xl border {{ $event['reminder_sent'] ? 'border-slate-200 text-slate-600' : 'border-amber-200 text-amber-700' }} px-4 py-2 text-sm font-bold">{{ $event['reminder_sent'] ? 'Quitar resaltado' : 'Resaltar cita' }}</button></form>
                        <a href="{{ route('citas.show', ['id' => $event['id']]) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Abrir</a>
                        <a href="{{ route('citas.edit', ['id' => $event['id']]) }}" class="rounded-xl bg-[#9700cf] px-4 py-2 text-sm font-bold text-white">Editar</a>
                    @else
                        <span class="mr-auto rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">Agenda de otro doctor · Solo consulta</span>
                    @endif
                    <button type="button" onclick="this.closest('dialog')?.close()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-500">Cerrar</button>
                </div>
            </div>
        </dialog>
    @endforeach

    <dialog id="calendar-block-dialog" class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form method="POST" action="{{ route('calendario.bloqueos.store') }}" class="bg-white">
            @csrf
            <div class="border-b border-slate-100 px-6 py-5">
                @if(!$isOperationalAdmin)
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#9700cf]">Disponibilidad</p>
                @endif
                <h2 class="text-2xl font-bold text-slate-900">Marcar d&iacute;a no disponible</h2>
                @if(!$isOperationalAdmin)
                    <p class="mt-1 text-sm text-slate-500">Ese d&iacute;a quedar&aacute; cerrado para nuevas citas.</p>
                @endif
            </div>
            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                @if($isOperationalAdmin)
                    <label class="sm:col-span-2"><span class="text-sm font-semibold text-slate-700">Doctor o asistente</span><select name="target" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"><optgroup label="Doctores">@foreach($dentists as $dentist)<option value="dentist:{{ $dentist->id }}">{{ $dentist->nombre }}</option>@endforeach</optgroup><optgroup label="Asistentes">@foreach($assistants as $assistant)<option value="assistant:{{ $assistant->id }}">{{ $assistant->name }}</option>@endforeach</optgroup></select></label>
                @elseif($isAssistant)
                    <label class="sm:col-span-2"><span class="text-sm font-semibold text-slate-700">Doctor</span><select name="dentist_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">@foreach($dentists as $dentist)<option value="{{ $dentist->id }}">{{ $dentist->nombre }}</option>@endforeach</select></label>
                @endif
                <label><span class="text-sm font-semibold text-slate-700">Fecha</span><input type="date" name="date" value="{{ $selectedDate->toDateString() }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></label>
                <label><span class="text-sm font-semibold text-slate-700">Motivo</span><select name="type" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">@foreach(['Festivo','No disponible','Vacaciones','Mantenimiento'] as $type)<option>{{ $type }}</option>@endforeach</select></label>
                <label class="sm:col-span-2"><span class="text-sm font-semibold text-slate-700">Notas</span><textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"></textarea></label>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                <button type="button" onclick="this.closest('dialog')?.close()" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">Cancelar</button>
                <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Guardar d&iacute;a</button>
            </div>
        </form>
    </dialog>
</div>

@endsection




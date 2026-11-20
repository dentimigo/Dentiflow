@extends('layouts.app')
@section('title', 'Agenda')
@section('content')
@php
    $activeWorkspace = request()->attributes->get('clinic_workspace');
    $role = session('access_role');
    $isDoctor = $role === 'doctor';
    $isOperationalAdmin = $role === 'admin';
    $statusStyles = [
        'Confirmada' => 'bg-[#f6e8ff] text-[#8200b8]',
        'Pendiente' => 'bg-violet-50 text-violet-700',
        'Cancelada' => 'bg-fuchsia-50 text-fuchsia-700',
        'Asistida' => 'bg-[#f6e8ff] text-[#8200b8]',
        'Terminada' => 'bg-emerald-50 text-emerald-700',
    ];
    $previousDate = match($view) {
        'dia' => $selectedDate->copy()->subDay(),
        'semana' => $selectedDate->copy()->subWeek(),
        default => $selectedDate->copy()->subMonth(),
    };
    $nextDate = match($view) {
        'dia' => $selectedDate->copy()->addDay(),
        'semana' => $selectedDate->copy()->addWeek(),
        default => $selectedDate->copy()->addMonth(),
    };
    $periodTitle = match($view) {
        'dia' => ucfirst($selectedDate->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')),
        'semana' => $weekDays->first()['date']->locale('es')->isoFormat('D MMM').' — '.$weekDays->last()['date']->locale('es')->isoFormat('D MMM YYYY'),
        default => ucfirst($selectedDate->locale('es')->isoFormat('MMMM [de] YYYY')),
    };
    $nextAppointment = $proximasCitas->first();
    $summaryCards = $isDoctor
        ? [
            ['Citas de hoy', $resumen['citas_hoy'], 'Programadas para hoy', 'bg-[#f6e8ff] text-[#9700cf]'],
            ['Pacientes', number_format($resumen['pacientes']), 'Expedientes activos', 'bg-[#f6e8ff] text-[#9700cf]'],
            ['Tratamientos realizados', number_format($resumen['tratamientos_realizados_mes']), 'Completados este mes', 'bg-[#fbf0ff] text-[#a126bb]'],
            [
                'Próxima cita',
                $nextAppointment['paciente'] ?? 'Agenda libre',
                $nextAppointment
                    ? date('H:i', strtotime($nextAppointment['hora'])).' · '.$nextAppointment['tipo']
                    : 'No tienes más citas programadas',
                'bg-violet-50 text-violet-600',
            ],
        ]
        : [
            ['Citas de hoy', $resumen['citas_hoy'], 'Programadas para hoy', 'bg-[#f6e8ff] text-[#9700cf]'],
            ['Pacientes', number_format($resumen['pacientes']), 'Expedientes activos', 'bg-[#f6e8ff] text-[#9700cf]'],
            ['Citas pendientes', $appointmentStatus['Pendiente'], 'Requieren seguimiento', 'bg-violet-50 text-violet-600'],
            ['Dentistas', number_format($resumen['dentistas']), 'Equipo activo', 'bg-[#fbf0ff] text-[#a126bb]'],
        ];
@endphp

<div class="space-y-5">
    @if(session('access_denied'))
        <div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-medium text-violet-700">{{ session('access_denied') }}</div>
    @endif

    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ $isDoctor ? 'Resumen clínico' : ($isOperationalAdmin ? 'Resumen' : 'Resumen de recepción') }}</h1>
            @if($activeWorkspace?->trial_ends_at && (int) $activeWorkspace->owner_user_id === (int) auth()->id())
                <a href="{{ route('clinic.plan') }}" class="mt-2 inline-block text-sm font-semibold underline">
                    @if($activeWorkspace->stripe_status === 'active')
                        Gestionar suscripción
                    @else
                        La prueba termina el {{ $activeWorkspace->trial_ends_at->format('d/m/Y H:i') }}
                    @endif
                </a>
            @endif
            <p class="mt-1 text-sm text-slate-500">{{ $isDoctor ? 'Consulta tu agenda, pacientes y actividad del día.' : ($isOperationalAdmin ? 'Vista general de las actividades y operaciones diarias del consultorio.' : 'Revisa citas, pacientes y tareas del consultorio.') }}</p>
        </div>
        <a href="{{ route('citas.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]"><span class="text-lg leading-none">+</span>Nueva cita</a>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($summaryCards as [$label, $value, $detail, $color])
            @php
                $isNextAppointmentCard = $isDoctor && $loop->last;
            @endphp
            <article class="flex min-h-[116px] items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $color }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                <div class="min-w-0 flex-1">
                    <p class="{{ $isNextAppointmentCard ? 'max-w-full text-base leading-5' : 'text-xl' }} font-bold text-slate-800">{{ $value }}</p>
                    <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $label }}</p>
                    <p class="mt-0.5 truncate text-[10px] text-slate-400">{{ $detail }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section>
        <div class="mb-3 flex items-center justify-between">
            <div><h2 class="text-sm font-bold text-slate-800">Acciones rápidas</h2><p class="text-[11px] text-slate-400">Accesos frecuentes</p></div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                [route('pacientes.create'), 'Nuevo paciente', 'Crear expediente clínico', '＋', 'bg-[#ead2fb] text-[#9700cf]'],
                [route('citas.create'), 'Agendar cita', 'Programar una consulta', '◷', 'bg-[#fbf0ff] text-[#a800f4]'],
                [($isDoctor || $isOperationalAdmin) ? route('pacientes.tratamiento') : route('inventario'), ($isDoctor || $isOperationalAdmin) ? 'Nuevo tratamiento' : 'Ver inventario', ($isDoctor || $isOperationalAdmin) ? 'Agregar al catálogo' : 'Revisar existencias', ($isDoctor || $isOperationalAdmin) ? '✦' : '□', 'bg-[#fbf0ff] text-[#b27acc]'],
                [route('citas'), 'Gestionar citas', 'Consultar la agenda', '☷', 'bg-[#f8e7ff] text-[#a126bb]'],
            ] as [$url, $label, $detail, $icon, $style])
                <a href="{{ $url }}" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-[0_4px_15px_rgba(30,34,60,.03)] transition hover:-translate-y-0.5 hover:border-[#d3a2f2] hover:shadow-md">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg {{ $style }}">{{ $icon }}</span>
                    <span class="min-w-0"><strong class="block text-xs text-slate-700 group-hover:text-[#9700cf]">{{ $label }}</strong><small class="mt-0.5 block truncate text-[10px] text-slate-400">{{ $detail }}</small></span>
                    <span class="ml-auto text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#9700cf]">›</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_24px_rgba(30,34,60,.05)]">
        <header class="border-b border-slate-100 p-4 sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('inicio', ['view' => $view, 'date' => $previousDate->toDateString()]) }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50">‹</a>
                    <a href="{{ route('inicio', ['view' => $view, 'date' => now()->toDateString()]) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50">Hoy</a>
                    <a href="{{ route('inicio', ['view' => $view, 'date' => $nextDate->toDateString()]) }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50">›</a>
                    <h2 class="ml-2 hidden text-base font-bold text-slate-800 sm:block">{{ $periodTitle }}</h2>
                </div>
                <div class="flex rounded-xl bg-slate-100 p-1">
                    @foreach(['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes'] as $key => $label)
                        <a href="{{ route('inicio', ['view' => $key, 'date' => $selectedDate->toDateString()]) }}" class="flex-1 rounded-lg px-4 py-2 text-center text-xs font-bold transition sm:flex-none {{ $view === $key ? 'bg-white text-[#9700cf] shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <h2 class="mt-3 text-sm font-bold text-slate-800 sm:hidden">{{ $periodTitle }}</h2>
        </header>

        @if($view === 'dia')
            <div class="p-4 sm:p-6">
                <div class="space-y-0">
                    @foreach(range(7, 20) as $hour)
                        @php $hourEvents = $selectedDayEvents->filter(fn ($event) => (int) date('G', strtotime($event['hora'])) === $hour); @endphp
                        <div class="grid min-h-20 grid-cols-[58px_1fr] border-b border-slate-100">
                            <div class="border-r border-slate-100 py-3 pr-3 text-right text-[11px] font-semibold text-slate-400">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00</div>
                            <div class="space-y-2 p-2">
                                @foreach($hourEvents as $event)
                                    <a href="{{ route('citas.show', $event['id']) }}" class="flex flex-col gap-2 rounded-xl p-3 sm:flex-row sm:items-center" style="border-left: 4px solid {{ $event['color'] ?? '#9700cf' }}; background-color: {{ $event['color'] ?? '#9700cf' }}18;">
                                        <strong class="text-xs" style="color: {{ $event['color'] ?? '#9700cf' }};">{{ date('H:i', strtotime($event['hora'])) }}</strong>
                                        <span class="text-sm font-bold text-slate-700">{{ $event['paciente'] }}</span>
                                        <span class="text-xs text-slate-400">{{ $event['tipo'] }} · {{ $event['dentista'] }}</span>
                                        <span class="rounded-full px-2 py-1 text-[9px] font-bold sm:ml-auto {{ $statusStyles[$event['estado']] ?? 'bg-slate-100 text-slate-600' }}">{{ $event['estado'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($view === 'semana')
            <div class="overflow-x-auto">
                <div class="min-w-[900px]">
                    <div class="grid grid-cols-7 divide-x divide-slate-100 border-b border-slate-100 bg-white">
                        @foreach($weekDays as $day)
                            <div class="p-3 text-center">
                                <p class="text-[10px] font-bold uppercase tracking-wider {{ $day['date']->isToday() ? 'text-[#9700cf]' : 'text-slate-400' }}">{{ $day['date']->locale('es')->isoFormat('ddd') }}</p>
                                <span class="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $day['date']->isToday() ? 'bg-[#9700cf] text-white' : 'text-slate-700' }}">{{ $day['date']->format('d') }}</span>
                                <p class="mt-1 text-[9px] font-semibold text-slate-400">{{ $day['events']->count() }} {{ $day['events']->count() === 1 ? 'cita' : 'citas' }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 divide-x divide-slate-100">
                    @foreach($weekDays as $day)
                        <div class="min-h-[460px] bg-white p-2">
                            <div class="space-y-2">
                                @forelse($day['events'] as $event)
                                    <a href="{{ route('citas.show', $event['id']) }}" class="relative z-0 block min-h-[86px] w-full overflow-hidden rounded-xl p-3 shadow-sm transition hover:shadow-md" style="border: 2px solid {{ $event['color'] ?? '#9700cf' }}; background-color: {{ $event['color'] ?? '#9700cf' }}14;">
                                        <div class="flex items-center justify-between gap-1">
                                            <p class="text-[11px] font-bold" style="color: {{ $event['color'] ?? '#9700cf' }};">{{ date('H:i', strtotime($event['hora'])) }}</p>
                                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $event['color'] ?? '#9700cf' }};"></span>
                                        </div>
                                        <p class="mt-2 truncate text-xs font-bold text-slate-800">{{ $event['paciente'] }}</p>
                                        <p class="mt-1 truncate text-[10px] text-slate-500">{{ $event['tipo'] }}</p>
                                        <p class="mt-1 truncate text-[9px] text-slate-400">{{ $event['dentista'] }}</p>
                                    </a>
                                @empty
                                    <p class="py-8 text-center text-[10px] text-slate-300">Sin citas</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if($view === 'mes')
            <div class="overflow-x-auto p-3 sm:p-5">
                <div class="min-w-[760px]">
                    <div class="grid grid-cols-7 border-b border-slate-100">
                        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)<div class="py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $weekday }}</div>@endforeach
                    </div>
                    <div class="grid grid-cols-7">
                        @foreach($calendarDays as $cell)
                            <div class="calendar-cell min-h-32 border-b border-r border-slate-100 p-2 {{ !$cell['day'] ? 'bg-slate-50/60' : 'bg-white' }}" @if($cell['date']) data-date="{{ $cell['date'] }}" @endif>
                                @if($cell['day'])
                                    <div class="mb-2 flex items-center justify-between"><span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $cell['date'] === now()->toDateString() ? 'bg-[#9700cf] text-white' : 'text-slate-600' }}">{{ $cell['day'] }}</span><span class="text-[9px] text-slate-300">{{ count($cell['events']) ?: '' }}</span></div>
                                    <div class="space-y-1">
                                        @foreach(array_slice($cell['events'], 0, 3) as $event)
                                            <a href="{{ route('citas.show', $event['id']) }}" class="draggable-event block rounded-md px-2 py-1.5 text-[10px] font-semibold" style="border-left: 3px solid {{ $event['color'] ?? '#9700cf' }}; background-color: {{ $event['color'] ?? '#9700cf' }}24; color: {{ $event['color'] ?? '#9700cf' }};" draggable="true" data-event-id="{{ $event['id'] }}" data-current-date="{{ $event['fecha'] }}" data-time="{{ date('H:i', strtotime($event['hora'])) }}" data-end-time="{{ $event['hora_fin'] ?? '' }}" data-patient="{{ $event['paciente'] }}" data-treatment="{{ $event['tipo'] }}" data-dentist="{{ $event['dentista'] }}" data-move-url="{{ route('citas.calendar.move', ['id' => $event['id']]) }}">
                                                <span class="block truncate"><span class="font-bold">{{ date('H:i', strtotime($event['hora'])) }}</span> {{ $event['paciente'] }}</span>
                                                <span class="mt-0.5 block truncate text-[9px] opacity-80">Dr: {{ $event['dentista'] }}</span>
                                            </a>
                                        @endforeach
                                        @if(count($cell['events']) > 3)<span class="block px-2 text-[9px] font-semibold text-slate-400">+{{ count($cell['events']) - 3 }} citas más</span>@endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.55fr_1fr]">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)] sm:p-6">
            <div class="flex items-center justify-between">
                <div><h2 class="font-bold text-slate-900">Actividad semanal</h2><p class="mt-0.5 text-xs text-slate-400">Citas programadas esta semana</p></div>
                <a href="{{ route('citas') }}" class="text-xs font-semibold text-[#9700cf]">Ver todas →</a>
            </div>
            <div class="mt-8 flex h-52 items-end gap-3 border-b border-slate-100 px-1 sm:gap-5">
                @foreach($weeklyAppointments as $day)
                    <div class="flex h-full flex-1 flex-col justify-end text-center">
                        <span class="mb-2 text-[10px] font-bold text-slate-400">{{ $day['count'] }}</span>
                        <div class="mx-auto w-full max-w-10 rounded-t-lg {{ $day['today'] ? 'bg-[#9700cf]' : 'bg-[#d3a2f2]' }}" style="height: {{ max(8, ($day['count'] / $maxWeeklyAppointments) * 155) }}px"></div>
                        <div class="mt-3 pb-3"><p class="text-[10px] font-semibold uppercase {{ $day['today'] ? 'text-[#9700cf]' : 'text-slate-400' }}">{{ $day['label'] }}</p><p class="text-xs font-bold text-slate-600">{{ $day['date'] }}</p></div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)] sm:p-6">
            <div><h2 class="font-bold text-slate-900">Estado de las citas</h2><p class="mt-0.5 text-xs text-slate-400">Distribución general</p></div>
            @php
                $confirmed = ($appointmentStatus['Confirmada'] / $totalAppointments) * 100;
                $pending = ($appointmentStatus['Pendiente'] / $totalAppointments) * 100;
                $attended = ($appointmentStatus['Asistida'] / $totalAppointments) * 100;
                $statusColors = ['Confirmada' => '#9700cf', 'Pendiente' => '#ad79d3', 'Asistida' => '#a800f4', 'Terminada' => '#059669', 'Cancelada' => '#a126bb'];
            @endphp
            <div class="mt-6 flex flex-col items-center gap-6 sm:flex-row xl:flex-col 2xl:flex-row">
                <div class="relative h-36 w-36 shrink-0 rounded-full" style="background: conic-gradient(#9700cf 0 {{ $confirmed }}%, #ad79d3 {{ $confirmed }}% {{ $confirmed + $pending }}%, #a800f4 {{ $confirmed + $pending }}% {{ $confirmed + $pending + $attended }}%, #a126bb {{ $confirmed + $pending + $attended }}% 100%)"><div class="absolute inset-[15px] flex flex-col items-center justify-center rounded-full bg-white"><strong class="text-2xl text-slate-900">{{ $appointmentStatus->sum() }}</strong><span class="text-[10px] text-slate-400">Total</span></div></div>
                <div class="w-full space-y-3">
                    @foreach($appointmentStatus as $status => $count)
                        <div class="flex items-center text-xs"><span class="mr-2 h-2.5 w-2.5 rounded-full" style="background: {{ $statusColors[$status] }}"></span><span class="text-slate-500">{{ $status }}</span><strong class="ml-auto text-slate-700">{{ $count }}</strong><span class="ml-2 w-8 text-right text-[10px] text-slate-400">{{ round(($count / $totalAppointments) * 100) }}%</span></div>
                    @endforeach
                </div>
            </div>
        </article>
    </section>

    @if(!$isDoctor)
        <section>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
                <div class="flex items-center justify-between"><div><h2 class="font-bold text-slate-900">Alertas de inventario</h2><p class="mt-0.5 text-xs text-slate-400">Productos por reponer</p></div><a href="{{ route('inventario') }}" class="text-xs font-semibold text-[#9700cf]">Ver todo</a></div>
                <div class="mt-4 space-y-3">
                    @forelse($inventoryAlerts as $item)
                        <div class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-fuchsia-50 text-xs font-bold text-fuchsia-500">!</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-700">{{ $item->material }}</p><p class="text-[10px] text-slate-400">Mínimo: {{ $item->reposicion }}</p></div><span class="rounded-full bg-fuchsia-50 px-2 py-1 text-[10px] font-bold text-fuchsia-600">{{ $item->stock }} uds.</span></div>
                    @empty<div class="rounded-xl bg-[#f6e8ff] p-3 text-xs font-medium text-[#8200b8]">Inventario con existencias suficientes.</div>@endforelse
                </div>
            </article>
        </section>
    @endif

    @if($isDoctor || $isOperationalAdmin)
        <section>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Últimos tratamientos</h2><p class="mt-0.5 text-xs text-slate-400">Resumen de los tratamientos registrados</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse($tratamientos as $tratamiento)
                        <div class="flex items-center gap-3 px-5 py-3.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#ead2fb] text-[#9700cf]">✦</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-700">{{ $tratamiento['tratamiento'] }}</p><p class="truncate text-[10px] text-slate-400">{{ $tratamiento['tipo'] }} · {{ $tratamiento['fecha'] }}</p></div><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusStyles[$tratamiento['estado']] ?? 'bg-violet-50 text-violet-700' }}">{{ $tratamiento['estado'] }}</span></div>
                    @empty<div class="p-8 text-center text-xs text-slate-400">No hay tratamientos registrados.</div>@endforelse
                </div>
            </article>
        </section>
    @endif
</div>
@endsection




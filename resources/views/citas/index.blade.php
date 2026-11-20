@extends('layouts.app')

@section('title','Gestión de Citas')

@section('content')

@php
    $citasCollection = $citas instanceof \Illuminate\Pagination\AbstractPaginator
        ? collect($citas->items())
        : collect($citas);
    $isDoctor = session('access_role') === 'doctor';
    $visibleEstados = collect($estados)->reject(fn ($estado) => in_array($estado, ['Cancelada', 'Terminada'], true))->values();

    $totalResultados = $citas instanceof \Illuminate\Pagination\AbstractPaginator
        ? $citas->total()
        : $citasCollection->count();

    $metricas = [
        [
            'label' => 'Citas de hoy',
            'value' => $resumen['hoy'] ?? $citasCollection->filter(fn($cita) => date('Y-m-d', strtotime($cita['fecha'])) === date('Y-m-d'))->count(),
            'detail' => 'Agenda del día',
            'tone' => 'border-[#d3a2f2] bg-[#f6e8ff] text-[#9700cf]',
        ],
        [
            'label' => 'Confirmadas',
            'value' => $resumen['confirmadas'] ?? $citasCollection->where('estado', 'Confirmada')->count(),
            'detail' => 'Listas para atender',
            'tone' => 'border-[#d3a2f2] bg-[#f6e8ff] text-[#8200b8]',
        ],
        [
            'label' => 'Pendientes',
            'value' => $resumen['pendientes'] ?? $citasCollection->where('estado', 'Pendiente')->count(),
            'detail' => 'Requieren confirmación',
            'tone' => 'border-violet-200 bg-violet-50 text-violet-700',
        ],
        [
            'label' => 'Próximas',
            'value' => $resumen['proximas'] ?? $citasCollection->count(),
            'detail' => 'Por atender',
            'tone' => 'border-violet-200 bg-violet-50 text-violet-700',
        ],
    ];

    $estadoClasses = [
        'Confirmada' => 'bg-[#ead2fb] text-[#8200b8] ring-purple-200',
        'Pendiente' => 'bg-violet-100 text-violet-800 ring-violet-200',
        'Cancelada' => 'bg-fuchsia-100 text-fuchsia-700 ring-rose-200',
        'Asistida' => 'bg-violet-100 text-violet-700 ring-violet-200',
        'Terminada' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-4xl font-bold text-slate-900">Citas</h1>
            <p class="mt-2 max-w-2xl text-slate-500">Agenda, confirma y da seguimiento a tus citas.</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('citas.historial') }}" class="inline-flex items-center justify-center rounded-lg border border-[#d3a2f2] bg-white px-5 py-3 text-sm font-semibold text-[#8200b8] shadow-sm transition hover:bg-[#fbf0ff]">
                Historial de citas
            </a>
            <a href="{{ route('calendario') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                Ver calendario
            </a>
            <a href="{{ route('citas.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#9700cf] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#512569]">
                Nueva cita
            </a>
        </div>
    </div>

    @include('partials.flash-success')

    @if($errors->any())
        <div class="rounded-lg border border-fuchsia-200 bg-fuchsia-50 p-4 text-sm font-semibold text-fuchsia-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach($metricas as $metrica)
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">{{ $metrica['label'] }}</p>
                        <p class="mt-3 text-3xl font-bold text-slate-950">{{ $metrica['value'] }}</p>
                    </div>
                    @if((int) $metrica['value'] > 0)
                        <span class="h-3 w-3 rounded-full border {{ $metrica['tone'] }}" aria-label="{{ $metrica['label'] }} con citas"></span>
                    @endif
                </div>
                <p class="mt-3 text-sm text-slate-500">{{ $metrica['detail'] }}</p>
            </div>
        @endforeach
    </div>

    <form action="{{ route('citas') }}" method="GET" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 {{ $isDoctor ? 'xl:grid-cols-[minmax(160px,1.2fr)_minmax(110px,0.7fr)_minmax(135px,0.75fr)_minmax(130px,0.8fr)_minmax(110px,auto)]' : 'xl:grid-cols-[minmax(160px,1.2fr)_minmax(110px,0.7fr)_minmax(135px,0.9fr)_minmax(135px,0.75fr)_minmax(130px,0.8fr)_minmax(110px,auto)]' }} md:items-end">
            <label class="block">
                <span class="text-sm font-medium text-slate-600">Buscar</span>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Paciente, dentista o tipo"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-900 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-600">Estado</span>
                <select name="estado" class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-700 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
                    <option value="Todos">Todos</option>
                    @foreach($visibleEstados as $estado)
                        <option value="{{ $estado }}" {{ request('estado', 'Todos') === $estado ? 'selected' : '' }}>{{ $estado }}</option>
                    @endforeach
                </select>
            </label>

            @unless($isDoctor)
                <label class="block">
                    <span class="text-sm font-medium text-slate-600">Dentista</span>
                    <select name="dentista" class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-700 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
                        <option value="Todos">Todos</option>
                        @foreach($dentistas as $dentista)
                            <option value="{{ $dentista }}" {{ request('dentista', 'Todos') === $dentista ? 'selected' : '' }}>{{ $dentista }}</option>
                        @endforeach
                    </select>
                </label>
            @endunless

            <label class="block">
                <span class="text-sm font-medium text-slate-600">Fecha</span>
                <input
                    type="date"
                    name="fecha"
                    value="{{ request('fecha') }}"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-900 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-600">Ordenar</span>
                <select name="order" class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-700 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
                    <option value="fecha_asc" {{ request('order', 'fecha_asc') === 'fecha_asc' ? 'selected' : '' }}>Próxima cita</option>
                    <option value="fecha_desc" {{ request('order') === 'fecha_desc' ? 'selected' : '' }}>Cita más reciente</option>
                    <option value="paciente_asc" {{ request('order') === 'paciente_asc' ? 'selected' : '' }}>Paciente A-Z</option>
                    @unless($isDoctor)
                        <option value="dentista_asc" {{ request('order') === 'dentista_asc' ? 'selected' : '' }}>Dentista A-Z</option>
                    @endunless
                </select>
            </label>

            <div class="flex min-w-0 gap-3 md:col-span-2 xl:col-span-1">
                <button type="submit" class="inline-flex min-w-[110px] flex-1 items-center justify-center whitespace-nowrap rounded-lg bg-[#9700cf] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#512569] xl:flex-none">
                    Aplicar
                </button>

                @if(count(request()->query()) > 0)
                    <a href="{{ route('citas') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Limpiar
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Agenda de citas</h2>
                <p class="text-sm text-slate-500">Mostrando {{ $totalResultados }} resultado{{ $totalResultados === 1 ? '' : 's' }}</p>
            </div>
            <a href="{{ route('citas.create') }}" class="text-sm font-semibold text-[#9700cf] hover:text-[#512569]">
                Agendar cita
            </a>
        </div>

        <div class="hidden overflow-x-auto xl:block">
            <table class="w-full min-w-[1120px] text-left">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-4 font-semibold">Paciente</th>
                        <th class="px-5 py-4 font-semibold">Fecha y hora</th>
                        <th class="px-5 py-4 font-semibold">Dentista</th>
                        <th class="px-5 py-4 font-semibold">Tipo</th>
                        <th class="px-5 py-4 font-semibold">Estado</th>
                        <th class="px-5 py-4 text-center font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($citas as $cita)
                        @php
                            $fechaCita = date('Y-m-d', strtotime($cita['fecha']));
                            $isToday = $fechaCita === date('Y-m-d');
                            $isPast = $fechaCita < date('Y-m-d');
                        @endphp
                        <tr
                            class="cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#9700cf]/30 {{ $isToday ? 'bg-[#f6e8ff]/70 hover:bg-[#f6e8ff]' : 'hover:bg-slate-50' }}"
                            data-edit-url="{{ route('citas.edit', ['id' => $cita['id']]) }}"
                            tabindex="0"
                            role="link"
                            aria-label="Editar cita de {{ $cita['paciente'] }}"
                            onclick="if (!event.target.closest('a, button, form, [popover], dialog')) window.location.href = this.dataset.editUrl"
                            onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, form, [popover], dialog')) { event.preventDefault(); window.location.href = this.dataset.editUrl; }">
                            <td class="px-5 py-4">
                                <a href="{{ route('citas.edit', ['id' => $cita['id']]) }}" class="font-semibold text-slate-900 transition hover:text-[#9700cf]">{{ $cita['paciente'] }}</a>
                                <p class="mt-1 text-sm text-slate-500">Cita #{{ $cita['id'] }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-800">{{ date('d/m/Y', strtotime($cita['fecha'])) }}</p>
                                <p class="mt-1 text-sm {{ $isPast ? 'text-fuchsia-600' : 'text-slate-500' }}">{{ $cita['horario'] ?? $cita['hora'] }}{{ $isToday ? ' · Hoy' : '' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $cita['dentista'] }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $cita['tipo'] }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $estadoClasses[$cita['estado']] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                    {{ $cita['estado'] }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-center">
                                    <button type="button" popovertarget="appointment-actions-desktop-{{ $cita['id'] }}" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]" aria-label="Acciones de cita #{{ $cita['id'] }}">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="18" cy="12" r="1.7" /></svg>
                                    </button>
                                    <div id="appointment-actions-desktop-{{ $cita['id'] }}" popover class="w-56 overflow-hidden rounded-2xl border border-[#ead2fb] bg-white p-0 text-left shadow-2xl">
                                        <div class="border-b border-slate-100 px-5 py-4">
                                            <p class="font-bold text-slate-800">Acciones</p>
                                            <p class="mt-1 truncate text-xs text-slate-400">Cita #{{ $cita['id'] }}</p>
                                        </div>
                                        <div class="space-y-1 p-2 text-sm font-semibold">
                                            <a href="{{ route('citas.show', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-[#9700cf] transition hover:bg-[#f6e8ff]">Detalle</a>
                                            <a href="{{ route('citas.edit', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Editar</a>
                                            @if($cita['estado'] === 'Pendiente')
                                                <a href="{{ route('citas.confirmar', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Confirmar</a>
                                            @endif
                                            @if(!in_array($cita['estado'], ['Asistida', 'Cancelada', 'Terminada']))
                                                <a href="{{ route('citas.asistida', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Asistida</a>
                                            @endif
                                            @if(!in_array($cita['estado'], ['Cancelada', 'Terminada']))
                                                <button type="button" onclick="document.getElementById('finish-appointment-{{ $cita['id'] }}')?.showModal(); this.closest('[popover]')?.hidePopover();" class="block w-full rounded-xl px-3 py-2.5 text-left text-emerald-700 transition hover:bg-emerald-50">Terminar</button>
                                                <button type="button" onclick="document.getElementById('cancel-appointment-{{ $cita['id'] }}')?.showModal(); this.closest('[popover]')?.hidePopover();" class="block w-full rounded-xl px-3 py-2.5 text-left text-fuchsia-600 transition hover:bg-fuchsia-50">Cancelar</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-12 text-center text-slate-500" colspan="6">
                                No hay citas que coincidan con los filtros actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 xl:hidden">
            @forelse($citas as $cita)
                @php
                    $fechaCita = date('Y-m-d', strtotime($cita['fecha']));
                    $isToday = $fechaCita === date('Y-m-d');
                    $isPast = $fechaCita < date('Y-m-d');
                @endphp
                <article
                    class="cursor-pointer p-5 transition hover:bg-[#fbf5ff] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#9700cf]/30 {{ $isToday ? 'bg-[#f6e8ff]/70' : '' }}"
                    data-edit-url="{{ route('citas.edit', ['id' => $cita['id']]) }}"
                    tabindex="0"
                    role="link"
                    aria-label="Editar cita de {{ $cita['paciente'] }}"
                    onclick="if (!event.target.closest('a, button, form, [popover], dialog')) window.location.href = this.dataset.editUrl"
                    onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, form, [popover], dialog')) { event.preventDefault(); window.location.href = this.dataset.editUrl; }">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Cita #{{ $cita['id'] }}</p>
                            <h3 class="mt-1 text-base font-semibold text-slate-900">
                                <a href="{{ route('citas.edit', ['id' => $cita['id']]) }}" class="transition hover:text-[#9700cf]">{{ $cita['paciente'] }}</a>
                            </h3>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $estadoClasses[$cita['estado']] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                {{ $cita['estado'] }}
                            </span>
                            <div>
                                <button type="button" popovertarget="appointment-actions-mobile-{{ $cita['id'] }}" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]" aria-label="Acciones de cita #{{ $cita['id'] }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="18" cy="12" r="1.7" /></svg>
                                </button>
                                <div id="appointment-actions-mobile-{{ $cita['id'] }}" popover class="w-56 overflow-hidden rounded-2xl border border-[#ead2fb] bg-white p-0 text-left shadow-2xl">
                                    <div class="border-b border-slate-100 px-5 py-4">
                                        <p class="font-bold text-slate-800">Acciones</p>
                                        <p class="mt-1 truncate text-xs text-slate-400">Cita #{{ $cita['id'] }}</p>
                                    </div>
                                    <div class="space-y-1 p-2 text-sm font-semibold">
                                        <a href="{{ route('citas.show', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-[#9700cf] transition hover:bg-[#f6e8ff]">Detalle</a>
                                        <a href="{{ route('citas.edit', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Editar</a>
                                        @if($cita['estado'] === 'Pendiente')
                                            <a href="{{ route('citas.confirmar', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Confirmar</a>
                                        @endif
                                        @if(!in_array($cita['estado'], ['Asistida', 'Cancelada', 'Terminada']))
                                            <a href="{{ route('citas.asistida', ['id' => $cita['id']]) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Asistida</a>
                                        @endif
                                        @if(!in_array($cita['estado'], ['Cancelada', 'Terminada']))
                                            <button type="button" onclick="document.getElementById('finish-appointment-{{ $cita['id'] }}')?.showModal(); this.closest('[popover]')?.hidePopover();" class="block w-full rounded-xl px-3 py-2.5 text-left text-emerald-700 transition hover:bg-emerald-50">Terminar</button>
                                            <button type="button" onclick="document.getElementById('cancel-appointment-{{ $cita['id'] }}')?.showModal(); this.closest('[popover]')?.hidePopover();" class="block w-full rounded-xl px-3 py-2.5 text-left text-fuchsia-600 transition hover:bg-fuchsia-50">Cancelar</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Fecha</dt>
                            <dd class="mt-1 font-medium text-slate-800">{{ date('d/m/Y', strtotime($cita['fecha'])) }}{{ $isToday ? ' · Hoy' : '' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Hora</dt>
                            <dd class="mt-1 font-medium {{ $isPast ? 'text-fuchsia-600' : 'text-slate-800' }}">{{ $cita['horario'] ?? $cita['hora'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Dentista</dt>
                            <dd class="mt-1 font-medium text-slate-800">{{ $cita['dentista'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Tipo</dt>
                            <dd class="mt-1 font-medium text-slate-800">{{ $cita['tipo'] }}</dd>
                        </div>
                    </dl>

                </article>
            @empty
                <div class="px-5 py-12 text-center text-slate-500">
                    No hay citas que coincidan con los filtros actuales.
                </div>
            @endforelse
        </div>
    </div>

    @if(is_object($citas) && method_exists($citas, 'links'))
        <div class="rounded-lg bg-white p-4 shadow-sm">
            {{ $citas->links('pagination::tailwind') }}
        </div>
    @endif

    @foreach($citasCollection as $cita)
        @if(!in_array($cita['estado'], ['Cancelada', 'Terminada']))
            @include('citas.partials.action-dialogs', ['cita' => $cita, 'catalogTreatments' => $catalogTreatments])
        @endif
    @endforeach
</div>

@endsection






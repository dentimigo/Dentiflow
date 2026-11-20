@extends('layouts.app')

@section('title', 'Expedientes')

@section('content')
@php
    $expedientesCollection = $expedientes instanceof \Illuminate\Pagination\AbstractPaginator
        ? collect($expedientes->items())
        : collect($expedientes);
@endphp

<div class="space-y-6 pt-6">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-4xl font-bold text-slate-900">Expedientes</h1>
            <p class="mt-2 max-w-2xl text-slate-500">Consulta datos clínicos, antecedentes y citas del paciente.</p>
        </div>

        <a href="{{ route('pacientes.create', ['origen' => 'expedientes']) }}" class="inline-flex items-center justify-center rounded-lg bg-[#9700cf] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#512569]">
            Nuevo expediente
        </a>
    </header>

    <form action="{{ route('expedientes') }}" method="GET" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 lg:grid-cols-[minmax(220px,1.5fr)_minmax(170px,0.9fr)_auto] lg:items-end">
            <label class="block">
                <span class="text-sm font-medium text-slate-600">Buscar expediente</span>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Paciente, teléfono o correo"
                    class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-900 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-600">Ordenar</span>
                <select name="order" class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-700 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
                    <option value="reciente" @selected($order === 'reciente')>Actividad reciente</option>
                    <option value="consultas_desc" @selected($order === 'consultas_desc')>Más consultas</option>
                    <option value="nombre_asc" @selected($order === 'nombre_asc')>Nombre A-Z</option>
                    <option value="nombre_desc" @selected($order === 'nombre_desc')>Nombre Z-A</option>
                </select>
            </label>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-[#9700cf] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#512569] lg:flex-none">
                    Aplicar
                </button>

                @if(count(request()->query()) > 0)
                    <a href="{{ route('expedientes') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Limpiar
                    </a>
                @endif
            </div>
        </div>
    </form>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Archivo de expedientes</h2>
                <p class="text-sm text-slate-500">Mostrando {{ $expedientesCollection->count() }} resultado{{ $expedientesCollection->count() === 1 ? '' : 's' }}</p>
            </div>
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[820px] text-left">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-4 font-semibold">Paciente</th>
                        <th class="px-5 py-4 font-semibold">Consultas</th>
                        <th class="px-5 py-4 font-semibold">Citas</th>
                        <th class="px-5 py-4 font-semibold">Tratamientos</th>
                        <th class="px-5 py-4 font-semibold">Actividad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($expedientes as $paciente)
                        @php
                            $initials = collect(explode(' ', trim($paciente->nombre)))->filter()->map(fn ($segment) => mb_strtoupper(mb_substr($segment, 0, 1)))->take(2)->join('');
                            $lastClinical = $paciente->clinical_records_max_recorded_at ? \Illuminate\Support\Carbon::parse($paciente->clinical_records_max_recorded_at)->format('d/m/Y') : null;
                            $lastAppointment = $paciente->appointments_max_date ? \Illuminate\Support\Carbon::parse($paciente->appointments_max_date)->format('d/m/Y') : null;
                        @endphp
                        <tr class="transition hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <a href="{{ route('pacientes.expediente', ['paciente' => $paciente, 'tab' => 'resumen']) }}" class="block">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#ead2fb] text-sm font-bold text-[#9700cf] ring-1 ring-purple-200">{{ $initials ?: 'PX' }}</span>
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $paciente->nombre }}</p>
                                            <p class="mt-1 text-sm text-slate-500">@if($paciente->email){{ $paciente->email }}@elseif($paciente->telefono)<a href="tel:{{ preg_replace('/\D+/', '', $paciente->telefono) }}" class="font-semibold text-[#9700cf]">{{ $paciente->telefono }}</a>@else Sin contacto @endif</p>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $paciente->clinical_records_count }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $paciente->appointments_count }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $paciente->treatments_count }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $lastClinical ?? $lastAppointment ?? 'Sin actividad' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-12 text-center text-slate-500" colspan="5">
                                No se encontraron expedientes con los filtros actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 lg:hidden">
            @forelse($expedientes as $paciente)
                @php
                    $initials = collect(explode(' ', trim($paciente->nombre)))->filter()->map(fn ($segment) => mb_strtoupper(mb_substr($segment, 0, 1)))->take(2)->join('');
                    $lastClinical = $paciente->clinical_records_max_recorded_at ? \Illuminate\Support\Carbon::parse($paciente->clinical_records_max_recorded_at)->format('d/m/Y') : null;
                    $lastAppointment = $paciente->appointments_max_date ? \Illuminate\Support\Carbon::parse($paciente->appointments_max_date)->format('d/m/Y') : null;
                @endphp
                            <article class="p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#ead2fb] text-sm font-bold text-[#9700cf] ring-1 ring-purple-200">{{ $initials ?: 'PX' }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('pacientes.expediente', ['paciente' => $paciente, 'tab' => 'resumen']) }}" class="block">
                                <h3 class="truncate text-base font-semibold text-slate-900">{{ $paciente->nombre }}</h3>
                                <p class="mt-1 truncate text-sm text-slate-500">@if($paciente->email){{ $paciente->email }}@elseif($paciente->telefono)<a href="tel:{{ preg_replace('/\D+/', '', $paciente->telefono) }}" class="font-semibold text-[#9700cf]">{{ $paciente->telefono }}</a>@else Sin contacto @endif</p>
                            </a>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-slate-500">Consultas</dt><dd class="mt-1 font-medium text-slate-800">{{ $paciente->clinical_records_count }}</dd></div>
                        <div><dt class="text-slate-500">Citas</dt><dd class="mt-1 font-medium text-slate-800">{{ $paciente->appointments_count }}</dd></div>
                        <div><dt class="text-slate-500">Tratamientos</dt><dd class="mt-1 font-medium text-slate-800">{{ $paciente->treatments_count }}</dd></div>
                        <div><dt class="text-slate-500">Actividad</dt><dd class="mt-1 font-medium text-slate-800">{{ $lastClinical ?? $lastAppointment ?? 'Sin actividad' }}</dd></div>
                    </dl>

                </article>
            @empty
                <div class="px-5 py-12 text-center text-slate-500">
                    No se encontraron expedientes con los filtros actuales.
                </div>
            @endforelse
        </div>
    </section>

    @if(is_object($expedientes) && method_exists($expedientes, 'links'))
        <div class="rounded-lg bg-white p-4 shadow-sm">
            {{ $expedientes->links('pagination::tailwind') }}
        </div>
    @endif
</div>
@endsection

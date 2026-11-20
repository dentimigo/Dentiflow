@extends('layouts.app')

@section('title', 'Administrar reportes')

@section('content')
<div class="space-y-6">
    @include('partials.flash-success')

    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Configuracion de reportes</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Revisa ganancias generadas, corrige citas terminadas o elimina reportes registrados por error.</p>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
        @foreach([
            ['Reportes realizados', number_format($summary['reports']), 'Tratamientos con ganancia'],
            ['Ganancias registradas', '$'.number_format($summary['earnings'], 2), 'Total de reportes'],
            ['Citas terminadas', number_format($summary['finishedAppointments']), 'Historial clinico'],
        ] as [$label, $value, $detail])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
                <p class="text-xs font-bold uppercase tracking-wider text-[#9700cf]">{{ $label }}</p>
                <p class="mt-4 text-2xl font-bold text-slate-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $detail }}</p>
            </article>
        @endforeach
    </section>

    <form method="GET" action="{{ route('admin.reports') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
        <div class="flex flex-col gap-3 sm:flex-row">
            <input type="search" name="search" value="{{ $search }}" placeholder="Buscar paciente, tratamiento o doctor" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#8200b8]">Buscar</button>
            @if($search !== '')
                <a href="{{ route('admin.reports') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
            @endif
        </div>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold text-slate-900">Reportes generados</h2>
            <p class="mt-0.5 text-xs text-slate-400">Eliminar un reporte no borra la cita terminada.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-4 font-semibold">Paciente</th>
                        <th class="px-5 py-4 font-semibold">Tratamiento</th>
                        <th class="px-5 py-4 font-semibold">Cita</th>
                        <th class="px-5 py-4 font-semibold">Doctor</th>
                        <th class="px-5 py-4 font-semibold">Ganancia</th>
                        <th class="px-5 py-4 font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reports as $report)
                        @php
                            $appointment = $report->appointment;
                            $start = $appointment?->time ? date('h:i A', strtotime($appointment->time)) : 'Sin hora';
                            $end = $appointment?->end_time ? date('h:i A', strtotime($appointment->end_time)) : null;
                        @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-800">{{ $report->patient?->nombre ?? $report->paciente }}</p>
                                <p class="mt-1 text-xs text-slate-400">Reporte #{{ $report->id }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $report->tratamiento }}</td>
                            <td class="px-5 py-4 text-slate-600">
                                @if($appointment)
                                    <p>{{ $appointment->date?->format('d/m/Y') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $start }}{{ $end ? ' - '.$end : '' }}</p>
                                @else
                                    <span class="text-slate-400">Sin cita vinculada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $appointment?->dentist?->nombre ?? 'Sin doctor' }}</td>
                            <td class="px-5 py-4 font-semibold text-emerald-700">${{ number_format($report->costo, 2) }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    @if($appointment && $appointment->estado === 'Terminada')
                                        <a href="{{ route('admin.reports.appointments.edit', $appointment) }}" class="text-sm font-semibold text-[#9700cf] hover:text-[#8200b8]">Editar cita</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reports.destroy', $report) }}" onsubmit="return confirm('¿Eliminar este reporte? La cita terminada se conservará.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm font-semibold text-fuchsia-600 hover:text-fuchsia-700">Eliminar reporte</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">No hay reportes generados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $reports->links('pagination::tailwind') }}</div>
        @endif
    </section>
</div>
@endsection

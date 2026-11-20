@extends('layouts.app')

@section('title', 'Historial de Pacientes')

@section('content')

<div>

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-4xl font-bold text-gray-800">Historial de Pacientes</h1>
            <p class="mt-2 text-gray-500">Registros recientes de citas y actividades de los pacientes.</p>
        </div>
        <a href="{{ route('pacientes') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">&larr; Volver</a>
    </div>

    @include('partials.flash-success')

    <div class="grid gap-6 lg:grid-cols-3 mb-8">
        <div class="bg-white rounded-3xl shadow-lg p-6">
            <p class="text-sm text-gray-500">Total de pacientes</p>
            <p class="mt-4 text-4xl font-bold text-[#9700cf]">{{ $totalPacientes }}</p>
        </div>

        <div class="bg-white rounded-3xl shadow-lg p-6">
            <p class="text-sm text-gray-500">Total de dinero</p>
            <p class="mt-4 text-4xl font-bold text-[#9700cf]">${{ number_format($totalDinero, 0, ',', '.') }}</p>
        </div>

        <div class="bg-white rounded-3xl shadow-lg p-6">
            <p class="text-sm text-gray-500">Tratamientos</p>
            <ul class="mt-4 space-y-2 text-gray-700">
                @foreach($tratamientos as $tratamiento => $cantidad)
                    <li class="flex items-center justify-between rounded-2xl bg-gray-50 px-4 py-3">
                        <span>{{ $tratamiento }}</span>
                        <span class="font-semibold text-[#9700cf]">{{ $cantidad }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="overflow-x-auto rounded-3xl bg-white shadow-lg">

        <table class="w-full min-w-[900px]">

            <thead class="bg-[#9700cf] text-white">
                <tr>
                    <th class="px-6 py-4 text-left">Paciente</th>
                    <th class="px-6 py-4 text-left">N.º de cita</th>
                    <th class="px-6 py-4 text-left">Actividad</th>
                    <th class="px-6 py-4 text-left">Fecha</th>
                    <th class="px-6 py-4 text-left">Hora de cita</th>
                    <th class="px-6 py-4 text-left">Estado</th>
                    <th class="px-6 py-4 text-center">Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($historial as $item)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-6 py-4 text-gray-700">{{ $item['paciente'] }}</td>
                        <td class="px-6 py-4 text-gray-700">
                            @if($item['appointment_id'])
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">Cita #{{ $item['appointment_id'] }}</span>
                            @else
                                <span class="text-xs text-slate-400">Sin cita</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ $item['tratamiento'] }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ $item['fecha'] }}</td>
                        <td class="px-6 py-4 text-gray-700">
                            <p class="font-semibold">{{ $item['horario'] }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $item['dentista'] }}</p>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <span class="{{ $item['estado'] === 'Cancelada' ? 'bg-fuchsia-100 text-fuchsia-700' : 'bg-[#ead2fb] text-[#8200b8]' }} px-3 py-1 rounded-full">
                                {{ $item['estado'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center">
                                <button type="button" popovertarget="patient-history-actions-{{ $item['treatment_id'] }}" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]" aria-label="Acciones de {{ $item['paciente'] }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="18" cy="12" r="1.7" /></svg>
                                </button>
                                <div id="patient-history-actions-{{ $item['treatment_id'] }}" popover class="w-56 overflow-hidden rounded-2xl border border-[#ead2fb] bg-white p-0 text-left shadow-2xl">
                                    <div class="border-b border-slate-100 px-5 py-4">
                                        <p class="font-bold text-slate-800">Acciones</p>
                                        <p class="mt-1 truncate text-xs text-slate-400">{{ $item['paciente'] }}</p>
                                    </div>
                                    <div class="space-y-1 p-2 text-sm font-semibold">
                                @if($item['appointment_id'])
                                    <a href="{{ route('citas.show', $item['appointment_id']) }}" class="block rounded-xl px-3 py-2.5 text-[#9700cf] transition hover:bg-[#f6e8ff]">Detalles</a>
                                    <a href="{{ route('citas.edit', $item['appointment_id']) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Editar</a>
                                @endif
                                <form method="POST" action="{{ route('pacientes.historial.destroy', $item['treatment_id']) }}" onsubmit="return confirm('¿Eliminar este registro, su cita y los datos económicos asociados? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="block w-full rounded-xl px-3 py-2.5 text-left text-fuchsia-600 transition hover:bg-fuchsia-50">Eliminar</button>
                                </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-6 py-10 text-center text-gray-500" colspan="7">
                            No hay historial disponible.
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

</div>

@endsection

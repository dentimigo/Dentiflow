@extends('layouts.app')

@section('title','Detalle de Cita')

@section('content')

<div>

    <div class="flex justify-between items-center mb-8">

        <div>

            <h1 class="text-4xl font-bold text-gray-800">
                Detalle de cita
            </h1>
            <p class="mt-2 text-gray-500">Revisa información completa de la cita seleccionada.</p>

        </div>

        <a href="{{ $backUrl }}" class="bg-gray-200 text-gray-800 px-6 py-3 rounded-2xl hover:bg-gray-300">
            &larr; Volver
        </a>

    </div>

    <div class="bg-white rounded-3xl shadow-lg p-8">
        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl bg-gray-50 p-6">
                <p class="text-sm text-gray-500">Paciente</p>
                <p class="mt-3 text-2xl font-semibold text-gray-800">{{ $cita['paciente'] }}</p>
            </div>
            <div class="rounded-3xl bg-gray-50 p-6">
                <p class="text-sm text-gray-500">{{ !empty($cita['collaborative']) ? 'Doctores participantes' : 'Dentista' }}</p>
                <p class="mt-3 text-2xl font-semibold text-gray-800">{{ $cita['dentista'] }}@if(!empty($cita['collaborators'])) + {{ implode(' + ', $cita['collaborators']) }}@endif</p>
                @if(!empty($cita['collaborative']))<span class="mt-3 inline-flex rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-700">Cita colaborativa</span>@endif
            </div>
            <div class="rounded-3xl bg-gray-50 p-6">
                <p class="text-sm text-gray-500">Fecha</p>
                <p class="mt-3 text-2xl font-semibold text-gray-800">{{ date('d/m/Y', strtotime($cita['fecha'])) }}</p>
            </div>
            <div class="rounded-3xl bg-gray-50 p-6">
                <p class="text-sm text-gray-500">Horario</p>
                <p class="mt-3 text-2xl font-semibold text-gray-800">{{ $cita['horario'] ?? $cita['hora'] }}</p>
            </div>
            <div class="rounded-3xl bg-gray-50 p-6">
                <p class="text-sm text-gray-500">Tipo de servicio</p>
                <p class="mt-3 text-2xl font-semibold text-gray-800">{{ $cita['tipo'] }}</p>
            </div>
            <div class="rounded-3xl bg-gray-50 p-6">
                <p class="text-sm text-gray-500">Estado</p>
                <p class="mt-3 text-2xl font-semibold text-gray-800">{{ $cita['estado'] }}</p>
            </div>
        </div>

        <section class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50/60 p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-emerald-700">Resumen econ&oacute;mico</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-800">Costos de la cita</h2>
                </div>
                <p class="text-2xl font-bold text-emerald-700">Total: ${{ number_format($finalCost, 2) }}</p>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-white p-4">
                    <p class="text-xs text-slate-400">Costo base</p>
                    <p class="mt-1 font-bold text-slate-700">${{ number_format($baseCost, 2) }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4">
                    <p class="text-xs text-slate-400">Procedimiento extra</p>
                    <p class="mt-1 font-semibold text-slate-700">{{ $cita['procedimiento_extra'] ?: 'Ninguno' }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4">
                    <p class="text-xs text-slate-400">Costo adicional</p>
                    <p class="mt-1 font-bold {{ $extraCost > 0 ? 'text-emerald-700' : 'text-slate-500' }}">+${{ number_format($extraCost, 2) }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4">
                    <p class="text-xs text-slate-400">Descuento</p>
                    <p class="mt-1 font-bold {{ $discount > 0 ? 'text-red-600' : 'text-slate-500' }}">{{ number_format($discount, 2) }}%</p>
                </div>
            </div>
        </section>

        <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:justify-end">
            <a href="{{ route('citas.edit', ['id' => $cita['id'], 'return_to' => $backUrl]) }}" class="inline-flex items-center justify-center rounded-2xl border border-[#9700cf] px-6 py-3 text-[#9700cf] hover:bg-[#f6e8ff]">
                Editar cita
            </a>
            @if($cita['estado'] === 'Pendiente')
                <a href="{{ route('citas.confirmar', ['id' => $cita['id']]) }}" class="inline-flex items-center justify-center rounded-2xl bg-[#ead2fb] px-6 py-3 text-[#8200b8] hover:bg-[#d3a2f2]">
                    Confirmar
                </a>
            @endif
            @if(!in_array($cita['estado'], ['Cancelada', 'Terminada']))
                <button type="button" onclick="document.getElementById('finish-appointment-{{ $cita['id'] }}')?.showModal();" class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-6 py-3 text-white hover:bg-emerald-700">
                    Terminar y registrar ganancia
                </button>
            @endif
            @if(!in_array($cita['estado'], ['Cancelada', 'Terminada']))
                <button type="button" onclick="document.getElementById('cancel-appointment-{{ $cita['id'] }}')?.showModal();" class="inline-flex items-center justify-center rounded-2xl bg-violet-100 px-6 py-3 text-violet-700 hover:bg-violet-200">
                    Cancelar
                </button>
            @endif
        </div>

    </div>

    @if(!in_array($cita['estado'], ['Cancelada', 'Terminada']))
        @include('citas.partials.action-dialogs', ['cita' => $cita, 'catalogTreatments' => $catalogTreatments])
    @endif

</div>

@endsection



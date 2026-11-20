@extends('layouts.app')

@section('title', 'Editar cita terminada')

@section('content')
<div class="space-y-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editar cita terminada</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Corrige la información histórica de la cita y sincroniza el reporte generado.</p>
        </div>
        <a href="{{ route('admin.reports') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">Volver a reportes</a>
    </section>

    <form method="POST" action="{{ route('admin.reports.appointments.update', $appointment) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_20px_rgba(30,34,60,.04)]">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-3 text-sm font-semibold text-fuchsia-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Paciente</span>
                <select name="patient_id" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                    @foreach($patients as $patient)
                        <option value="{{ $patient->id }}" @selected((int) old('patient_id', $appointment->patient_id) === $patient->id)>{{ $patient->nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Doctor</span>
                <select name="dentist_id" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                    @foreach($dentists as $dentist)
                        <option value="{{ $dentist->id }}" @selected((int) old('dentist_id', $appointment->dentist_id) === $dentist->id)>{{ $dentist->nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Fecha</span>
                <input type="date" name="date" value="{{ old('date', $appointment->date?->toDateString()) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Tratamiento</span>
                <select name="tipo" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                    @foreach($catalogTreatments as $treatment)
                        <option value="{{ $treatment->tratamiento }}" @selected(old('tipo', $appointment->tipo) === $treatment->tratamiento)>
                            {{ $treatment->tratamiento }} - {{ $treatment->tipo }} - ${{ number_format($treatment->costo, 2) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Hora de inicio</span>
                <input type="time" name="time" value="{{ old('time', date('H:i', strtotime($appointment->time))) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </label>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Hora de terminado</span>
                <input type="time" name="end_time" value="{{ old('end_time', $appointment->end_time ? date('H:i', strtotime($appointment->end_time)) : date('H:i', strtotime($appointment->time))) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </label>
        </div>

        <div class="mt-6 rounded-2xl border border-[#ead2fb] bg-[#fbf0ff] p-4 text-sm text-[#512569]">
            Cita #{{ $appointment->id }} marcada como terminada. Al guardar, el reporte vinculado se actualizará con paciente, tratamiento, fecha y costo del catálogo.
        </div>

        <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.reports') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#8200b8]">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection

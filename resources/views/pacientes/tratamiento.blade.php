@extends('layouts.app')

@section('title', $edit ? 'Editar tratamiento' : 'Nuevo tratamiento')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ $edit ? 'Editar tratamiento' : 'Nuevo tratamiento' }}</h1>
            <p class="mt-2 text-sm text-slate-500">Define el servicio y el color con el que se vera en el calendario.</p>
        </div>
        <a href="{{ route('pacientes.tratamientos') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600">Volver al catálogo</a>
    </div>

    <form method="POST" action="{{ $edit ? route('pacientes.tratamientos.update', $tratamiento['id']) : route('pacientes.tratamientos.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_4px_20px_rgba(30,34,60,.04)] sm:p-8">
        @csrf
        @if($edit) @method('PUT') @endif

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-4 text-sm text-fuchsia-700">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Nombre del tratamiento
                <input name="tratamiento" value="{{ old('tratamiento', $tratamiento['tratamiento'] ?? '') }}" placeholder="Ej. Limpieza dental" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3.5 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
            </label>

            <label class="block text-sm font-semibold text-slate-700">Tipo
                <select name="tipo" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3.5 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo }}" @selected(old('tipo', $tratamiento['tipo'] ?? '') === $tipo)>{{ $tipo }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-slate-700">Costo
                <div class="relative mt-2">
                    <span class="absolute left-4 top-3.5 text-slate-400">$</span>
                    <input type="number" min="0" step="0.01" name="costo" value="{{ old('costo', $tratamiento['costo'] ?? '') }}" placeholder="0.00" required class="w-full rounded-xl border border-slate-200 py-3.5 pl-8 pr-4 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
                </div>
            </label>

            <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Color en calendario
                <span class="mt-2 flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3.5">
                    <input type="color" name="color" value="{{ old('color', $tratamiento['color'] ?? '#9700cf') }}" class="h-10 w-14 cursor-pointer rounded-lg border-0 bg-transparent p-0">
                    <span class="text-sm font-normal text-slate-500">Las citas con este tratamiento usarán este color en día, semana y mes.</span>
                </span>
            </label>

            <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Descripcion
                <textarea name="descripcion" rows="5" placeholder="Describe el procedimiento, alcance o indicaciones" class="mt-2 w-full resize-none rounded-xl border border-slate-200 px-4 py-3.5 font-normal outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">{{ old('descripcion', $tratamiento['descripcion'] ?? '') }}</textarea>
            </label>
        </div>

        <div class="mt-7 flex justify-end gap-3">
            <a href="{{ route('pacientes.tratamientos') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600">Cancelar</a>
            <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]">{{ $edit ? 'Guardar cambios' : 'Registrar tratamiento' }}</button>
        </div>
    </form>
</div>
@endsection

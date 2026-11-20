@extends('layouts.app')

@section('title','Dentistas')

@section('content')

<div class="space-y-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Dentistas</h1>
            <p class="text-sm text-gray-500">Gestiona los profesionales del consultorio.</p>
        </div>
        <a href="{{ route('dentistas.create') }}" class="inline-flex w-full items-center justify-center rounded-lg bg-[#9700cf] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#512569] sm:w-auto">Agregar dentista</a>
    </div>

    @include('partials.flash-success')

    <div class="bg-white rounded-3xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[640px]">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-4 font-semibold">Nombre</th>
                        <th class="px-5 py-4 font-semibold">Especialidad</th>
                        <th class="px-5 py-4 font-semibold">Teléfono</th>
                        <th class="px-5 py-4 font-semibold">Acceso</th>
                        <th class="px-5 py-4 font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($dentistas as $dentista)
                        <tr>
                            <td class="px-5 py-4 font-medium text-slate-800">{{ $dentista->nombre }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $dentista->especialidad }}</td>
                            <td class="px-5 py-4"><a href="tel:{{ preg_replace('/\D+/', '', $dentista->telefono) }}" class="font-semibold text-[#9700cf] hover:text-[#512569]">{{ $dentista->telefono }}</a></td>
                            <td class="px-5 py-4 text-slate-600">{{ $dentista->user?->email ?? 'Sin acceso' }}</td>
                            <td class="px-5 py-4">
                                <a href="{{ route('dentistas.edit', ['id' => $dentista->id]) }}" class="text-[#9700cf] hover:text-[#512569] mr-4">Editar</a>
                                <a href="{{ route('dentistas.delete', ['id' => $dentista->id]) }}" class="text-fuchsia-600 hover:text-fuchsia-700">Eliminar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection





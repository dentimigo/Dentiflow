@extends('layouts.app')

@section('title', 'Tratamientos')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Tratamientos</h1>
            <p class="mt-2 text-sm text-slate-500">Gestiona tratamientos y su color en el calendario.</p>
        </div>
        <a href="{{ route('pacientes.tratamiento') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2]"><span class="text-lg">+</span>Nuevo tratamiento</a>
    </div>

    @include('partials.flash-success')

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_200px_180px_auto]">
        <input name="search" value="{{ request('search') }}" placeholder="Buscar tratamiento, tipo o descripción..." class="rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf] focus:ring-4 focus:ring-[#ead2fb]">
        <select name="tipo" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
            <option value="Todos">Todos los tipos</option>
            @foreach($tipos as $tipo)<option value="{{ $tipo }}" @selected(request('tipo', 'Todos') === $tipo)>{{ $tipo }}</option>@endforeach
        </select>
        <select name="order" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
            <option value="nombre_asc">Nombre A-Z</option>
            <option value="nombre_desc" @selected(request('order') === 'nombre_desc')>Nombre Z-A</option>
            <option value="costo_desc" @selected(request('order') === 'costo_desc')>Mayor costo</option>
            <option value="costo_asc" @selected(request('order') === 'costo_asc')>Menor costo</option>
        </select>
        <button class="rounded-xl bg-slate-800 px-5 py-3 text-sm font-bold text-white">Filtrar</button>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_20px_rgba(30,34,60,.04)]">
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-left">
                <thead class="border-b border-slate-100 bg-slate-50/70 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-4">Tratamiento</th>
                        <th class="px-5 py-4">Tipo</th>
                        <th class="px-5 py-4">Color</th>
                        <th class="px-5 py-4">Descripcion</th>
                        <th class="px-5 py-4 text-right">Costo</th>
                        <th class="px-5 py-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tratamientos as $item)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $item['tratamiento'] }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-[#f6e8ff] px-2.5 py-1 text-[10px] font-bold text-[#9700cf]">{{ $item['tipo'] }}</span></td>
                            <td class="px-5 py-4">
                                <span class="inline-flex h-6 w-6 rounded-lg border border-slate-200 shadow-sm" style="background-color: {{ $item['color'] }}" aria-label="Color asignado"></span>
                            </td>
                            <td class="max-w-sm px-5 py-4 text-xs text-slate-500">{{ $item['descripcion'] ?: 'Sin descripción' }}</td>
                            <td class="px-5 py-4 text-right font-bold">${{ number_format($item['costo'], 2, '.', ',') }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-center">
                                    <button type="button" popovertarget="treatment-actions-desktop-{{ $item['id'] }}" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]" aria-label="Acciones de {{ $item['tratamiento'] }}">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="18" cy="12" r="1.7" /></svg>
                                    </button>
                                    <div id="treatment-actions-desktop-{{ $item['id'] }}" popover class="w-56 overflow-hidden rounded-2xl border border-[#ead2fb] bg-white p-0 text-left shadow-2xl">
                                        <div class="border-b border-slate-100 px-5 py-4">
                                            <p class="font-bold text-slate-800">Acciones</p>
                                            <p class="mt-1 truncate text-xs text-slate-400">{{ $item['tratamiento'] }}</p>
                                        </div>
                                        <div class="space-y-1 p-2 text-sm font-semibold">
                                            <a href="{{ route('pacientes.tratamientos.edit', $item['id']) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Editar</a>
                                            <a href="{{ route('pacientes.tratamientos.delete', $item['id']) }}" class="block rounded-xl px-3 py-2.5 text-fuchsia-600 transition hover:bg-fuchsia-50">Eliminar</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400">No hay tratamientos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($tratamientos as $item)
                <article class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-4 w-4 rounded-md border border-slate-200" style="background-color: {{ $item['color'] }}"></span>
                                <h2 class="font-bold text-slate-800">{{ $item['tratamiento'] }}</h2>
                            </div>
                            <span class="mt-2 inline-flex rounded-full bg-[#f6e8ff] px-2 py-1 text-[10px] font-bold text-[#9700cf]">{{ $item['tipo'] }}</span>
                        </div>
                        <div class="flex shrink-0 items-start gap-2">
                            <strong>${{ number_format($item['costo'], 2) }}</strong>
                            <div>
                                <button type="button" popovertarget="treatment-actions-mobile-{{ $item['id'] }}" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]" aria-label="Acciones de {{ $item['tratamiento'] }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="18" cy="12" r="1.7" /></svg>
                                </button>
                                <div id="treatment-actions-mobile-{{ $item['id'] }}" popover class="w-56 overflow-hidden rounded-2xl border border-[#ead2fb] bg-white p-0 text-left shadow-2xl">
                                    <div class="border-b border-slate-100 px-5 py-4">
                                        <p class="font-bold text-slate-800">Acciones</p>
                                        <p class="mt-1 truncate text-xs text-slate-400">{{ $item['tratamiento'] }}</p>
                                    </div>
                                    <div class="space-y-1 p-2 text-sm font-semibold">
                                        <a href="{{ route('pacientes.tratamientos.edit', $item['id']) }}" class="block rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-[#f6e8ff] hover:text-[#9700cf]">Editar</a>
                                        <a href="{{ route('pacientes.tratamientos.delete', $item['id']) }}" class="block rounded-xl px-3 py-2.5 text-fuchsia-600 transition hover:bg-fuchsia-50">Eliminar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-slate-500">{{ $item['descripcion'] ?: 'Sin descripción' }}</p>
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-400">No hay tratamientos registrados.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection


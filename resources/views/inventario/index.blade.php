@extends('layouts.app')

@section('title', 'Inventario')

@section('content')

<div>
    <div class="relative mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-4xl font-bold text-gray-800">Inventario</h1>
            <p class="mt-2 text-gray-500">Control de stock, alertas de reposición y costos por proveedor.</p>
        </div>
        <details class="group relative w-full md:w-auto">
            <summary class="flex w-full cursor-pointer list-none items-center justify-center gap-2 rounded-2xl border border-[#d3a2f2] bg-white px-5 py-3 text-sm font-bold text-[#9700cf] shadow-sm transition hover:bg-[#fbf0ff] md:w-auto">
                Exportar inventario <span class="transition group-open:rotate-180">⌄</span>
            </summary>
            <div class="relative z-10 mt-2 w-full overflow-hidden rounded-2xl border border-[#ead2fb] bg-white shadow-2xl md:absolute md:right-0 md:w-[420px]">
                <div class="border-b border-slate-100 px-4 py-3"><p class="font-bold text-slate-800">Reportes de inventario</p><p class="mt-1 text-xs text-slate-400">Selecciona el contenido y formato de descarga.</p></div>
                <div class="divide-y divide-slate-100 p-2">
                    @foreach([
                        'completo' => ['Inventario completo', 'Todos los materiales y su estado actual'],
                        'alertas' => ['Productos por reponer', 'Materiales con cinco unidades o menos'],
                        'proveedores' => ['Costos por proveedor', 'Resumen agrupado por proveedor'],
                        'valor' => ['Valor del inventario', 'Stock por costo y valor total estimado'],
                    ] as $reportType => [$reportTitle, $reportDescription])
                        <div class="p-2.5 sm:flex sm:items-center sm:justify-between sm:gap-3">
                            <div class="min-w-0"><p class="text-sm font-bold text-slate-700">{{ $reportTitle }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $reportDescription }}</p></div>
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:mt-0 sm:flex sm:shrink-0">
                                <a href="{{ route('inventario.export.pdf', ['tipo' => $reportType]) }}" class="rounded-lg bg-[#9700cf] px-3 py-2 text-center text-xs font-bold text-white">PDF</a>
                                <a href="{{ route('inventario.export.excel', ['tipo' => $reportType]) }}" class="rounded-lg border border-[#d3a2f2] px-3 py-2 text-center text-xs font-bold text-[#9700cf]">Excel</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    </div>

    <div class="grid gap-6 md:grid-cols-3 mb-6">
        <div class="rounded-3xl bg-white p-6 shadow-lg">
            <p class="text-sm text-gray-500">Productos en alerta</p>
            <p class="mt-4 text-4xl font-bold text-[#9700cf]">{{ $alertas->count() }}</p>
            <p class="mt-2 text-sm text-gray-500">Materiales con 5 unidades o menos.</p>
        </div>
        <div class="rounded-3xl bg-white p-6 shadow-lg">
            <p class="text-sm text-gray-500">Proveedores activos</p>
            <p class="mt-4 text-4xl font-bold text-[#9700cf]">{{ $costosPorProveedor->count() }}</p>
            <p class="mt-2 text-sm text-gray-500">Número de proveedores registrados en inventario.</p>
        </div>
        <div class="rounded-3xl bg-white p-6 shadow-lg">
            <p class="text-sm text-gray-500">Stock total estimado</p>
            <p class="mt-4 text-4xl font-bold text-[#9700cf]">{{ $productos->sum('stock') }}</p>
            <p class="mt-2 text-sm text-gray-500">Unidades disponibles en stock.</p>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-lg p-6 mb-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800">Stock de materiales dentales</h2>
                <p class="text-sm text-gray-500">Revisa el estado actual de los materiales y sus proveedores.</p>
            </div>
            <a href="{{ route('inventario.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-[#9700cf] px-5 py-3 text-white hover:bg-[#512569]">Agregar material</a>
        </div>

        @include('partials.flash-success')

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-[#9700cf] text-white">
                    <tr>
                        <th class="px-6 py-4">Material</th>
                        <th class="px-6 py-4">Stock disponible</th>
                        <th class="px-6 py-4">Nivel de reposición</th>
                        <th class="px-6 py-4">Costo unitario</th>
                        <th class="px-6 py-4">Proveedor</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4 text-center">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productos as $producto)
                        <tr
                            class="cursor-pointer border-b transition hover:bg-[#fbf5ff] focus-within:bg-[#fbf5ff]"
                            data-edit-url="{{ route('inventario.edit', ['id' => $producto['id']]) }}"
                            tabindex="0"
                            role="link"
                            aria-label="Editar {{ $producto['material'] }}"
                            onclick="if (!event.target.closest('a, button, form')) window.location.href = this.dataset.editUrl"
                            onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, form')) { event.preventDefault(); window.location.href = this.dataset.editUrl; }">
                            <td class="px-6 py-4 font-semibold text-gray-800">
                                <a href="{{ route('inventario.edit', ['id' => $producto['id']]) }}" class="transition hover:text-[#9700cf]">{{ $producto['material'] }}</a>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $producto['stock'] }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $producto['reposicion'] }}</td>
                            <td class="px-6 py-4 text-gray-700">${{ number_format($producto['costo'], 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $producto['proveedor'] ?: 'Sin proveedor' }}</td>
                            <td class="px-6 py-4">
                                @php($stockStatus = $producto->stockStatus())
                                <span @class([
                                    'inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1',
                                    'bg-red-50 text-red-700 ring-red-200' => $stockStatus === 'low',
                                    'bg-amber-50 text-amber-700 ring-amber-200' => $stockStatus === 'running_out',
                                    'bg-sky-50 text-sky-700 ring-sky-200' => $stockStatus === 'available',
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200' => $stockStatus === 'high',
                                ])>{{ $producto->stockStatusLabel() }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a
                                    href="{{ route('inventario.delete', ['id' => $producto['id']]) }}"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-red-200 bg-white text-red-500 transition hover:bg-red-50 hover:text-red-700"
                                    aria-label="Eliminar {{ $producto['material'] }}"
                                    title="Eliminar material"
                                    onclick="event.stopPropagation(); return confirm('¿Deseas eliminar este material del inventario?')">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="bg-white rounded-3xl shadow-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Alertas de reposición</h2>
            <div class="space-y-4">
                @forelse($alertas as $item)
                    <div class="rounded-3xl border border-fuchsia-100 bg-fuchsia-50 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-gray-800">{{ $item['material'] }}</p>
                                <p class="text-sm text-gray-500">Proveedor: {{ $item['proveedor'] ?: 'Sin proveedor' }}</p>
                            </div>
                            <span @class([
                                'rounded-full px-3 py-1 text-sm font-semibold',
                                'bg-red-100 text-red-700' => $item->stockStatus() === 'low',
                                'bg-amber-100 text-amber-700' => $item->stockStatus() === 'running_out',
                            ])>{{ $item->stockStatusLabel() }}</span>
                        </div>
                        <p class="mt-3 text-gray-700">Cantidad disponible: {{ $item['stock'] }} · Nivel objetivo: {{ $item['reposicion'] }}</p>
                    </div>
                @empty
                    <div class="rounded-3xl border border-gray-200 p-4 text-gray-500">No hay alertas críticas en este momento.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Costos por proveedor</h2>
            <div class="space-y-4">
                @foreach($costosPorProveedor as $proveedor => $info)
                    <div class="rounded-3xl border border-gray-200 p-4">
                        <p class="font-semibold text-gray-800">{{ $proveedor === 'Sin proveedor' ? '' : $proveedor }}</p>
                        <p class="text-sm text-gray-500">Materiales: {{ $info['total_materiales'] }}</p>
                        <p class="mt-2 text-gray-700">Costo promedio: ${{ number_format($info['costo_promedio'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@endsection




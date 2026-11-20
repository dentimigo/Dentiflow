@extends('layouts.app')
@section('title', 'Gesti&oacute;n del sistema')
@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-950">Gesti&oacute;n del sistema</h1>
        </div>
    </header>

    @include('partials.flash-success')
    @error('backup')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</div>
    @enderror

    <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5"><h2 class="font-bold text-slate-900">Diagn&oacute;stico de servicios</h2><p class="mt-1 text-xs text-slate-400">Disponibilidad de componentes t&eacute;cnicos.</p></div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                @foreach($services as [$name, $status, $ok])
                    <div class="rounded-xl bg-slate-50 p-4"><div class="flex items-center justify-between"><p class="text-sm font-bold text-slate-700">{{ $name }}</p><span class="h-2.5 w-2.5 rounded-full {{ $ok ? 'bg-emerald-500' : 'bg-amber-500' }}"></span></div><p class="mt-2 text-xs font-semibold {{ $ok ? 'text-emerald-700' : 'text-amber-700' }}">{{ $status }}</p></div>
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Entorno de ejecuci&oacute;n</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach([
                    ['Entorno', strtoupper($runtime['environment'])],
                    ['Laravel', $runtime['laravel']],
                    ['PHP', $runtime['php']],
                    ['Base de datos', $runtime['databaseDriver'].' &middot; '.$runtime['databaseSize']],
                    ['Sesiones', $runtime['sessionDriver']],
                ] as [$label, $value])
                    <div class="flex items-center justify-between gap-4 py-3"><span class="text-xs font-semibold text-slate-500">{{ $label }}</span><span class="text-right text-xs font-bold text-slate-700">{{ $value }}</span></div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Herramientas de mantenimiento</h2>
        <p class="mt-1 text-xs text-slate-400">Acciones t&eacute;cnicas seguras y registradas.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div><h3 class="text-sm font-bold text-slate-800">Limpiar cach&eacute;</h3><p class="mt-1 text-xs leading-5 text-slate-500">Elimina cach&eacute;s temporales de configuraci&oacute;n, rutas, vistas y datos.</p></div>
                    <span class="shrink-0 rounded-full bg-[#f6e8ff] px-3 py-1 text-xs font-bold text-[#9700cf]">{{ $cache['formatted'] }}</span>
                </div>
                <p class="mt-3 text-xs text-slate-400">
                    {{ $cache['files'] }} archivos temporales
                    @if($cache['entries'] > 0)
                        &middot; {{ $cache['entries'] }} registros de cach&eacute;
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.system.cache') }}" class="mt-4">@csrf<button class="rounded-xl bg-[#9700cf] px-4 py-2.5 text-xs font-bold text-white">Limpiar cach&eacute;</button></form>
            </div>
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div><h3 class="text-sm font-bold text-slate-800">Respaldo de base de datos</h3><p class="mt-1 text-xs leading-5 text-slate-500">Genera y descarga una copia de la base {{ $backupDriver }} actual.</p></div>
                    <span class="shrink-0 rounded-full bg-[#f6e8ff] px-3 py-1 text-xs font-bold text-[#9700cf]">{{ $backupDriver }}</span>
                </div>
                @if($backupAvailable)
                    <p class="mt-3 text-xs text-slate-400">Herramienta de respaldo disponible</p>
                    <a href="{{ route('admin.system.backup') }}" class="mt-4 inline-flex rounded-xl bg-[#9700cf] px-4 py-2.5 text-xs font-bold text-white">Descargar respaldo</a>
                @else
                    <p class="mt-4 text-xs font-semibold text-slate-400">La herramienta de respaldo no est&aacute; disponible. Configura MYSQLDUMP_PATH.</p>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection


@extends('layouts.app')

@section('title', 'Historial de citas')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-4xl font-bold text-slate-900">Historial de citas</h1>
            <p class="mt-2 text-sm text-slate-500">{{ session('access_role') === 'doctor' ? 'Consulta y recupera tus citas canceladas.' : 'Consulta el historial del consultorio y recupera citas canceladas.' }}</p>
        </div>
        <a href="{{ route('citas') }}" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-semibold text-slate-600">&larr; Volver a citas</a>
    </header>

    @include('partials.flash-success')
    @if($errors->any())
        <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-4 text-sm font-semibold text-fuchsia-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="grid gap-3 rounded-2xl border border-[#ead2fb] bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]">
        <input type="search" name="search" value="{{ $search }}" placeholder="Paciente, doctor o tratamiento" class="rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf]">
        <select name="estado" class="rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-[#9700cf]">
            @foreach(['Todos', 'Cancelada', 'Terminada'] as $option)
                <option value="{{ $option }}" @selected($estado === $option)>{{ $option === 'Todos' ? 'Canceladas y terminadas' : $option }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white">Filtrar</button>
    </form>

    <section class="overflow-hidden rounded-2xl border border-[#ead2fb] bg-white shadow-[0_12px_40px_rgba(81,37,105,.08)]">
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[780px] text-left">
                <thead class="bg-[#9700cf] text-xs uppercase tracking-wider text-white">
                    <tr>
                        <th class="px-5 py-4">N.&ordm; de cita</th>
                        <th class="px-5 py-4">Paciente</th>
                        <th class="px-5 py-4">Doctor</th>
                        <th class="px-5 py-4">Fecha y hora</th>
                        <th class="px-5 py-4">Tratamiento</th>
                        <th class="px-5 py-4">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($citas as $cita)
                        <tr class="text-sm text-slate-600 hover:bg-[#fbf0ff]/50">
                            <td class="px-5 py-4 font-bold text-slate-700">#{{ $cita->id }}</td>
                            <td class="px-5 py-4"><a href="{{ route('citas.show', $cita->id) }}" class="font-semibold text-slate-800 transition hover:text-[#9700cf] hover:underline">{{ $cita->patient?->nombre ?? 'Sin paciente' }}</a></td>
                            <td class="px-5 py-4">{{ $cita->dentist?->nombre ?? 'Sin doctor' }}</td>
                            <td class="px-5 py-4">{{ $cita->date->format('d/m/Y') }} &middot; {{ date('H:i', strtotime($cita->time)) }}</td>
                            <td class="px-5 py-4">{{ $cita->tipo }}</td>
                            <td class="px-5 py-4"><span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $cita->estado === 'Cancelada' ? 'bg-fuchsia-100 text-fuchsia-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $cita->estado }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-sm text-slate-400">No hay citas en el historial con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($citas as $cita)
                <article class="p-4">
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <a href="{{ route('citas.show', $cita->id) }}" class="min-w-0 font-bold text-slate-900 transition active:text-[#9700cf]">
                            <span class="block break-words">{{ $cita->patient?->nombre ?? 'Sin paciente' }}</span>
                            <span class="mt-1 block text-xs font-semibold text-[#9700cf]">Ver detalles de la cita</span>
                        </a>
                        <span class="inline-flex shrink-0 whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-bold {{ $cita->estado === 'Cancelada' ? 'bg-fuchsia-100 text-fuchsia-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $cita->estado }}</span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div><dt class="text-xs font-semibold text-slate-400">N.&ordm; de cita</dt><dd class="mt-1 font-bold text-slate-700">#{{ $cita->id }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-400">Fecha y hora</dt><dd class="mt-1 font-semibold text-slate-700">{{ $cita->date->format('d/m/Y') }}<span class="block">{{ date('H:i', strtotime($cita->time)) }}</span></dd></div>
                        <div><dt class="text-xs font-semibold text-slate-400">Tratamiento</dt><dd class="mt-1 break-words font-semibold text-slate-700">{{ $cita->tipo }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-400">Doctor</dt><dd class="mt-1 break-words font-semibold text-slate-700">{{ $cita->dentist?->nombre ?? 'Sin doctor' }}</dd></div>
                    </dl>
                </article>
            @empty
                <p class="px-5 py-14 text-center text-sm text-slate-400">No hay citas en el historial con estos filtros.</p>
            @endforelse
        </div>
        @if($citas->hasPages())<div class="border-t border-slate-100 p-4">{{ $citas->links() }}</div>@endif
    </section>
</div>
@endsection


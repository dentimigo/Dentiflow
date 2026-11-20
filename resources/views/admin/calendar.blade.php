@extends('layouts.app')

@section('title', 'Calendario administrativo')

@section('content')
<div class="space-y-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Calendario del consultorio</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500">Consulta la agenda mensual por doctor sin mezclarla con los indicadores generales del dashboard.</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_4px_24px_rgba(30,34,60,.05)]">
        <header class="border-b border-slate-100 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Calendario por doctor</h2>
                    <p class="mt-1 text-xs text-slate-400">Filtra la agenda mensual para revisar disponibilidad individual.</p>
                </div>
                <form method="GET" action="{{ route('admin.calendar') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <select name="dentista" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 outline-none transition focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/20">
                        <option value="Todos" @selected($selectedDentist === 'Todos')>Todos los doctores</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) $selectedDentist === (string) $dentist->id)>{{ $dentist->nombre }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-[#9700cf] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#8200b8]">Filtrar</button>
                </form>
            </div>
            <div class="mt-4 flex items-center justify-between gap-3">
                <a href="{{ route('admin.calendar', ['year' => $prevMonth->format('Y'), 'month' => $prevMonth->format('n'), 'dentista' => $selectedDentist]) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50">Anterior</a>
                <p class="text-sm font-bold text-slate-800">{{ $monthName }} {{ $year }}</p>
                <a href="{{ route('admin.calendar', ['year' => $nextMonth->format('Y'), 'month' => $nextMonth->format('n'), 'dentista' => $selectedDentist]) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50">Siguiente</a>
            </div>
        </header>

        <div class="overflow-x-auto p-4">
            <div class="min-w-[760px]">
                <div class="grid grid-cols-7 border-b border-slate-100">
                    @foreach(['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $weekday)
                        <div class="py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $weekday }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7">
                    @foreach($calendarDays as $cell)
                        <div class="min-h-32 border-b border-r border-slate-100 p-2 {{ !$cell['day'] ? 'bg-slate-50/60' : 'bg-white' }}">
                            @if($cell['day'])
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $cell['date'] === now()->toDateString() ? 'bg-[#9700cf] text-white' : 'text-slate-600' }}">{{ $cell['day'] }}</span>
                                    <span class="text-[9px] text-slate-300">{{ count($cell['events']) ?: '' }}</span>
                                </div>
                                <div class="space-y-1">
                                    @foreach(array_slice($cell['events'], 0, 4) as $event)
                                        <a href="{{ route('citas.show', $event['id']) }}" class="block rounded-md px-2 py-1.5 text-[10px] font-semibold transition hover:brightness-95" style="border-left: 3px solid {{ $event['color'] ?? '#9700cf' }}; background-color: {{ $event['color'] ?? '#9700cf' }}24; color: {{ $event['color'] ?? '#9700cf' }};">
                                            <span class="font-bold">{{ date('H:i', strtotime($event['hora'])) }}</span> {{ $event['paciente'] }}
                                            <span class="block truncate text-[9px] font-medium text-slate-500">{{ $event['dentista'] }}</span>
                                        </a>
                                    @endforeach
                                    @if(count($cell['events']) > 4)
                                        <span class="block px-2 text-[9px] font-semibold text-slate-400">+{{ count($cell['events']) - 4 }} citas mas</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@php
    $baseCost = (float) ($cita['base_cost'] ?? 0);
@endphp

<dialog id="finish-appointment-{{ $cita['id'] }}" data-finish-dialog data-base-cost="{{ $baseCost }}" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="POST" action="{{ route('citas.terminar', ['id' => $cita['id']]) }}" class="bg-white">
        @csrf
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">Finalizar cita</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">Confirmar cierre economico</h2>
            <p class="mt-1 text-sm text-slate-500">Revisa la cita y registra cualquier ajuste antes de marcarla como terminada.</p>
        </div>

        <div class="space-y-5 px-6 py-5">
            <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Paciente</p><p class="mt-1 font-semibold text-slate-800">{{ $cita['paciente'] }}</p></div>
                <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Tratamiento</p><p class="mt-1 font-semibold text-slate-800">{{ $cita['tipo'] }}</p></div>
                <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Fecha</p><p class="mt-1 font-semibold text-slate-800">{{ date('d/m/Y', strtotime($cita['fecha'])) }}</p></div>
                <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Hora</p><p class="mt-1 font-semibold text-slate-800">{{ $cita['horario'] ?? $cita['hora'] }}</p></div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Descuento porcentual</span>
                    <div class="mt-2 flex rounded-2xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-emerald-200">
                        <input data-finish-discount type="number" name="descuento" min="0" max="100" step="0.01" value="{{ $cita['descuento'] ?? 0 }}" class="min-w-0 flex-1 rounded-l-2xl px-4 py-3 outline-none">
                        <span class="px-4 py-3 text-slate-400">%</span>
                    </div>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Procedimiento extra</span>
                    <input type="text" name="procedimiento_extra" maxlength="255" value="{{ $cita['procedimiento_extra'] ?? '' }}" placeholder="Opcional" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-emerald-200">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Costo adicional</span>
                    <div class="mt-2 flex rounded-2xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-emerald-200">
                        <span class="px-4 py-3 text-slate-400">$</span>
                        <input data-finish-extra type="number" name="costo_extra" min="0" step="0.01" value="{{ $cita['costo_extra'] ?? 0 }}" class="min-w-0 flex-1 rounded-r-2xl px-2 py-3 outline-none">
                    </div>
                </label>
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Descripci&oacute;n del descuento</span>
                <textarea name="completion_description" rows="3" maxlength="2000" placeholder="Explica el motivo del descuento aplicado" class="mt-2 w-full resize-y rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-emerald-200">{{ $cita['completion_description'] ?? '' }}</textarea>
                <span class="mt-1.5 block text-xs text-slate-400">Esta informaci&oacute;n explicar&aacute; el descuento en la cita y el tratamiento realizado.</span>
            </label>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <span class="text-sm font-semibold text-slate-600">Costo base: ${{ number_format($baseCost, 2) }}</span>
                <span class="text-lg font-bold text-emerald-700">Total: $<span data-finish-total>{{ number_format(max(0, ($baseCost + (float) ($cita['costo_extra'] ?? 0)) * (1 - ((float) ($cita['descuento'] ?? 0) / 100))), 2) }}</span></span>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
            <button type="button" data-dialog-close class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Volver</button>
            <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-100 transition hover:bg-emerald-700">Confirmar finalizacion</button>
        </div>
    </form>
</dialog>

<dialog id="cancel-appointment-{{ $cita['id'] }}" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="POST" action="{{ route('citas.cancelar', ['id' => $cita['id']]) }}" class="bg-white">
        @csrf
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-2xl font-bold text-slate-900">Cancelar cita</h2>
            <p class="mt-1 text-sm text-slate-500">La cita se conservara en el historial con estado cancelado.</p>
        </div>

        <div class="space-y-4 px-6 py-5">
            <div class="rounded-2xl bg-slate-50 p-4 text-sm">
                <p class="font-semibold text-slate-800">{{ $cita['paciente'] }}</p>
                <p class="mt-1 text-slate-500">{{ date('d/m/Y', strtotime($cita['fecha'])) }} · {{ $cita['horario'] ?? $cita['hora'] }}</p>
                <p class="mt-1 text-slate-500">{{ $cita['tipo'] }}</p>
            </div>
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Motivo de cancelacion</span>
                <textarea name="cancellation_reason" maxlength="500" rows="4" placeholder="Escribe el motivo si aplica" class="mt-2 w-full resize-none rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-fuchsia-200">{{ $cita['cancellation_reason'] ?? '' }}</textarea>
            </label>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
            <button type="button" data-dialog-close class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Volver</button>
            <button type="submit" class="rounded-xl bg-fuchsia-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-fuchsia-100 transition hover:bg-fuchsia-700">Cancelar cita</button>
        </div>
    </form>
</dialog>

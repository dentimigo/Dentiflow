@php
    $isDashboard = request()->routeIs('inicio', 'admin.dashboard');
    $isCalendar = request()->routeIs('calendario', 'admin.calendar');
    $isTable = request()->routeIs(
        'pacientes', 'pacientes.historial', 'pacientes.tratamientos', 'expedientes', 'dentistas',
        'citas', 'citas.historial', 'inventario', 'reportes', 'admin.users', 'admin.reports'
    );
@endphp

<div id="df-refresh" class="df-refresh" hidden>
    <div class="df-refresh__status" role="status">
        <svg class="df-refresh__tooth" width="42" height="42" viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <path d="M20 8.5C16 5 9 5 6.5 11c-2 4.7.3 9.1 2.4 12.3 2.6 4 2.1 11.7 5.8 11.7 3.4 0 1.7-11 5.3-11s1.9 11 5.3 11c3.7 0 3.2-7.7 5.8-11.7 2.1-3.2 4.4-7.6 2.4-12.3C31 5 24 5 20 8.5Z" fill="#f0dafa" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
            <path d="M20 8.5c1.6 1.4 3.6 2 5.4 1.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <span>Actualizando página…</span>
    </div>

    <div class="df-refresh__surface" aria-hidden="true">
        <div class="df-refresh__block df-refresh__heading"></div>
        <div class="df-refresh__block df-refresh__caption"></div>

        @if ($isDashboard || request()->routeIs('citas', 'inventario', 'reportes', 'pacientes.historial'))
            <div class="df-refresh__grid df-refresh__metrics">
                @for ($card = 0; $card < 4; $card++)
                    <div class="df-refresh__panel"><div class="df-refresh__block df-refresh__metric"></div></div>
                @endfor
            </div>
        @endif

        @if ($isDashboard || $isCalendar)
            <div class="df-refresh__panel df-refresh__grid df-refresh__calendar">
                @for ($day = 0; $day < 28; $day++)
                    <div class="df-refresh__block df-refresh__day"></div>
                @endfor
            </div>
        @elseif ($isTable)
            <div class="df-refresh__panel df-refresh__grid">
                <div class="df-refresh__block df-refresh__field"></div>
                <div class="df-refresh__block df-refresh__field"></div>
            </div>
            <div class="df-refresh__panel">
                @for ($row = 0; $row < 6; $row++)
                    <div class="df-refresh__block df-refresh__row"></div>
                @endfor
            </div>
        @else
            <div class="df-refresh__panel">
                <div class="df-refresh__block df-refresh__caption"></div>
                <div class="df-refresh__grid">
                    @for ($field = 0; $field < 6; $field++)
                        <div class="df-refresh__block df-refresh__field"></div>
                    @endfor
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    (() => {
        if (performance.getEntriesByType('navigation')[0]?.type !== 'reload') return;

        const placeholder = document.getElementById('df-refresh');
        const content = document.getElementById('df-refresh-content');
        const main = document.getElementById('main-content');
        const startedAt = performance.now();
        const minimumDuration = matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450;

        function revealContent() {
            placeholder.hidden = true;
            content.hidden = false;
            main.removeAttribute('aria-busy');
        }

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(revealContent, Math.max(0, minimumDuration - (performance.now() - startedAt)));
        }, { once: true });

        // Release the view if a deferred asset delays DOMContentLoaded.
        setTimeout(revealContent, 3500);
        main.setAttribute('aria-busy', 'true');
        content.hidden = true;
        placeholder.hidden = false;
    })();
</script>

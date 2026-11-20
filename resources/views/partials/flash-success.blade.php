@if(session('success'))
    <div data-flash-message class="flex items-center gap-3 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-4 text-sm font-semibold text-emerald-700 transition duration-500">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                <path d="m5 12 4 4L19 6"/>
            </svg>
        </span>
        <span>{{ session('success') }}</span>
    </div>
@endif


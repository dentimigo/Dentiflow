@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex items-center justify-between px-4 py-3 sm:px-6">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700 leading-5">
                    Mostrando
                    <span class="font-medium">{{ $paginator->firstItem() }}</span>
                    a
                    <span class="font-medium">{{ $paginator->lastItem() }}</span>
                    de
                    <span class="font-medium">{{ $paginator->total() }}</span>
                    resultados
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rounded-md shadow-sm">
                    @if ($paginator->onFirstPage())
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#9700cf] border border-gray-300 cursor-default leading-5 rounded-l-md">
                            Anterior
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#9700cf] border border-gray-300 rounded-l-md leading-5 hover:bg-[#512569] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9700cf]">
                            Anterior
                        </a>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-500 bg-white border border-gray-300 leading-5">{{ $element }}</span>
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-semibold text-white bg-[#9700cf] border border-gray-300 leading-5">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-[#9700cf] bg-white border border-gray-300 leading-5 hover:bg-[#f6e8ff] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9700cf]">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-white bg-[#9700cf] border border-gray-300 rounded-r-md leading-5 hover:bg-[#512569] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9700cf]">
                            Siguiente
                        </a>
                    @else
                        <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-white bg-[#9700cf] border border-gray-300 cursor-default rounded-r-md leading-5">
                            Siguiente
                        </span>
                    @endif
                </span>
            </div>
        </div>

        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#9700cf] border border-gray-300 cursor-default leading-5 rounded-md">
                    Anterior
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#9700cf] border border-gray-300 rounded-md leading-5 hover:bg-[#512569] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9700cf]">
                    Anterior
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#9700cf] border border-gray-300 rounded-md leading-5 hover:bg-[#512569] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9700cf]">
                    Siguiente
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-[#9700cf] border border-gray-300 cursor-default leading-5 rounded-md">
                    Siguiente
                </span>
            @endif
        </div>
    </nav>
@endif




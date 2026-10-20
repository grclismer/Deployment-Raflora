{{--
    The one pager for every paginated list (set as the default in AppServiceProvider), so search,
    filters and sort stay in the page links (the controllers use withQueryString()).
    Phones get Previous / "Page x of y" / Next; wider screens also get the page numbers.
--}}
@if ($paginator->hasPages())
    @php
        $link = 'inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500';
        $off = 'inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-medium text-slate-400 cursor-not-allowed';
        $hasTotal = method_exists($paginator, 'total');
    @endphp
    <nav role="navigation" aria-label="Pagination" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-slate-600">
            @if ($hasTotal && $paginator->firstItem())
                Showing <span class="font-semibold text-slate-800">{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}</span> of <span class="font-semibold text-slate-800">{{ $paginator->total() }}</span>
            @else
                Page {{ $paginator->currentPage() }}
            @endif
        </p>
        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="{{ $off }}" aria-disabled="true">&larr;<span class="ml-1 hidden sm:inline">Previous</span><span class="sr-only sm:hidden">Previous page</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}" aria-label="Previous page">&larr;<span class="ml-1 hidden sm:inline">Previous</span></a>
            @endif

            @if ($hasTotal)
                <span class="px-2 text-sm text-slate-600 sm:hidden">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            @endif
            <span class="hidden items-center gap-1.5 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-1 text-sm text-slate-500" aria-hidden="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-emerald-700 bg-emerald-700 px-3 text-sm font-bold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}" aria-label="Next page"><span class="mr-1 hidden sm:inline">Next</span>&rarr;</a>
            @else
                <span class="{{ $off }}" aria-disabled="true"><span class="mr-1 hidden sm:inline">Next</span><span class="sr-only sm:hidden">Next page</span>&rarr;</span>
            @endif
        </div>
    </nav>
@endif

{{-- Laravel supplies the bounded $elements list; DESIGN.md §5. --}}
@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="page-link" aria-disabled="true">Previous</span>
        @else
            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-gap">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-link" aria-current="page"
                            aria-label="Page {{ $page }}">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $url }}"
                            aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="page-link" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif

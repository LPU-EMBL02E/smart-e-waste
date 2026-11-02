{{--
    Replaces Laravel's default pagination view with the design system's: 44px
    targets that stay usable on a phone. web/docs/DESIGN.md §§5, 9.
--}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages">
        @if ($paginator->onFirstPage())
            <span class="pager-item" aria-disabled="true">Previous</span>
        @else
            <a class="pager-item" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pager-item pager-gap" aria-disabled="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pager-item" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pager-item" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pager-item" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="pager-item" aria-disabled="true">Next</span>
        @endif

        <p class="pager-summary">Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
    </nav>
@endif

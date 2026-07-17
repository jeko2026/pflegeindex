@if ($paginator->hasPages())
    <nav class="pagination-nav" aria-label="Seitennavigation">
        @if ($paginator->onFirstPage())
            <span class="is-disabled">Zurück</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Zurück</a>
        @endif

        <span class="page-status">Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Weiter</a>
        @else
            <span class="is-disabled">Weiter</span>
        @endif
    </nav>
@endif

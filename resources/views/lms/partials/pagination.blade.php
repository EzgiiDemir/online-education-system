@if($paginator->hasPages())
<nav class="pagination">
<a href="{{ $paginator->previousPageUrl() }}" class="button secondary{{ $paginator->onFirstPage() ? ' is-disabled' : '' }}"@if($paginator->onFirstPage()) aria-disabled="true" onclick="return false"@endif>← Önceki</a>
<span class="muted">Sayfa {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
<a href="{{ $paginator->nextPageUrl() }}" class="button secondary{{ !$paginator->hasMorePages() ? ' is-disabled' : '' }}"@unless($paginator->hasMorePages()) aria-disabled="true" onclick="return false"@endunless>Sonraki →</a>
</nav>
@endif

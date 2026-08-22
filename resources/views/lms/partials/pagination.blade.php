@php($en = session('locale', 'tr') === 'en')
@if($paginator->hasPages())
<nav class="pagination">
<a href="{{ $paginator->previousPageUrl() }}" class="button secondary{{ $paginator->onFirstPage() ? ' is-disabled' : '' }}"@if($paginator->onFirstPage()) aria-disabled="true" onclick="return false"@endif>← {{ $en ? 'Previous' : 'Önceki' }}</a>
<span class="muted">{{ $en ? 'Page' : 'Sayfa' }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
<a href="{{ $paginator->nextPageUrl() }}" class="button secondary{{ !$paginator->hasMorePages() ? ' is-disabled' : '' }}"@unless($paginator->hasMorePages()) aria-disabled="true" onclick="return false"@endunless>{{ $en ? 'Next' : 'Sonraki' }} →</a>
</nav>
@endif

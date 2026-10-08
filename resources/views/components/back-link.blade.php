@props(['fallback'])
@php($en = session('locale', 'tr') === 'en')
<button type="button" class="back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ $fallback }}')"><x-lucide name="arrow-left" size="16" /> {{ $en ? 'Back' : 'Geri' }}</button>

{{-- Expects: $estimatedMinutes (nullable int) --}}
@php($en = session('locale', 'tr') === 'en')
@if($estimatedMinutes)
<p class="muted viewer-estimate"><x-lucide name="clock" size="14" /> {{ $en ? 'Estimated reading time: ~'.$estimatedMinutes.' min' : 'Yaklaşık okuma süresi: ~'.$estimatedMinutes.' dk' }}</p>
@endif

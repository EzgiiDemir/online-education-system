@extends('lms.layout')
@section('title', $material->title.' · Arucad Campus LMS')
@section('content')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}">← Öğrenim alanıma dön</a>
<div class="card surface viewer-card">
<span class="eyebrow">{{ $material->session->course->title }}</span>
<h1>{{ $material->title }}</h1>
@if(str_starts_with($material->type, 'video/'))
<div class="viewer-media"><video id="content-player" controls src="{{ asset('storage/'.$material->path) }}"></video></div>
@elseif($material->type === 'application/pdf')
<div class="viewer-media"><iframe src="{{ asset('storage/'.$material->path) }}"></iframe></div>
@else
<div class="viewer-empty"><p class="muted">Bu içerik türü tarayıcıda önizlenemiyor.</p><a class="button secondary" href="{{ asset('storage/'.$material->path) }}" target="_blank" rel="noopener">Dosyayı indir / aç</a></div>
@endif
<div class="viewer-progress">
<div class="viewer-progress-label"><span>İzlenme süresi</span><span id="watched-pct">%{{ $required ? min(100, (int) round($progress->watched_seconds / $required * 100)) : 0 }}</span></div>
<progress id="progress-bar" max="{{ $required }}" value="{{ min($progress->watched_seconds, $required) }}"></progress>
<small id="hint">@if($progress->completed)Tamamlandı@else<span id="watched">{{ $progress->watched_seconds }}</span> / {{ $required }} sn@endif</small>
</div>
@if($progress->completed)
<div class="viewer-done"><span class="status" style="background:#eef1e9;color:var(--success-dark)">✓ Tamamlandı</span></div>
@else
<div class="viewer-actions">
<form method="post" action="{{ route('materials.complete', $material) }}" id="complete-form">@csrf<button type="submit" id="complete-btn" disabled>Tamamlandı olarak işaretle</button></form>
<small class="muted">İçeriği görüntüledikçe süre otomatik birikir; yeterli süreye ulaşınca buton aktif olur.</small>
</div>
@endif
</div>
</div>
@if(!$progress->completed)
<script>
(function(){
    var required = {{ $required }};
    var btn = document.getElementById('complete-btn');
    var watchedEl = document.getElementById('watched');
    var pctEl = document.getElementById('watched-pct');
    var barEl = document.getElementById('progress-bar');
    var token = document.querySelector('meta[name="csrf-token"]').content;
    function tick(){
        if (document.visibilityState !== 'visible') return;
        fetch('{{ route('materials.heartbeat', $material) }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}
        }).then(function(r){ return r.json(); }).then(function(data){
            watchedEl.textContent = data.watched_seconds;
            pctEl.textContent = '%' + Math.min(100, Math.round(data.watched_seconds / required * 100));
            barEl.value = Math.min(data.watched_seconds, required);
            if (data.eligible && btn) btn.disabled = false;
        });
    }
    setInterval(tick, 5000);
    tick();
})();
</script>
@endif
@endsection

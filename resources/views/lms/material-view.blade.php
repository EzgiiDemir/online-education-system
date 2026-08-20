@extends('lms.layout')
@section('title', $material->title.' · Campus LMS')
@section('content')
<div class="card surface" style="max-width:720px;margin:0 auto">
<a href="{{ route('learning.index') }}">← Öğrenim alanıma dön</a>
<h1>{{ $material->title }}</h1>
@if(str_starts_with($material->type, 'video/'))
<video id="content-player" controls style="width:100%;border-radius:8px" src="{{ asset('storage/'.$material->path) }}"></video>
@elseif($material->type === 'application/pdf')
<iframe src="{{ asset('storage/'.$material->path) }}" style="width:100%;height:70vh;border:1px solid #eaecf0;border-radius:8px"></iframe>
@else
<p class="muted">Bu içerik türü tarayıcıda önizlenemiyor.</p>
<a class="button secondary" href="{{ asset('storage/'.$material->path) }}" target="_blank" rel="noopener">Dosyayı indir / aç</a>
@endif
<div style="margin-top:20px">
<p class="muted" id="progress-label">İzlenme süresi: <span id="watched">{{ $progress->watched_seconds }}</span> / {{ $required }} sn</p>
<progress id="progress-bar" max="{{ $required }}" value="{{ min($progress->watched_seconds, $required) }}" style="width:100%"></progress>
</div>
@if($progress->completed)
<p class="alert success">✓ Bu materyali tamamladınız.</p>
@else
<form method="post" action="{{ route('materials.complete', $material) }}" id="complete-form">@csrf<button class="button" type="submit" id="complete-btn" disabled>Tamamlandı olarak işaretle</button></form>
<p class="muted" id="hint">İçeriği görüntüledikçe süre otomatik birikir; yeterli süreye ulaşınca buton aktif olur.</p>
@endif
</div>
@if(!$progress->completed)
<script>
(function(){
    var required = {{ $required }};
    var btn = document.getElementById('complete-btn');
    var watchedEl = document.getElementById('watched');
    var barEl = document.getElementById('progress-bar');
    var token = document.querySelector('meta[name="csrf-token"]').content;
    function tick(){
        if (document.visibilityState !== 'visible') return;
        fetch('{{ route('materials.heartbeat', $material) }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}
        }).then(function(r){ return r.json(); }).then(function(data){
            watchedEl.textContent = data.watched_seconds;
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

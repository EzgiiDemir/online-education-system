@extends('lms.layout')
@section('title', $activity->title.' · Campus LMS')
@section('content')
<div class="card surface" style="max-width:720px;margin:0 auto">
<a href="{{ route('learning.index') }}">← Öğrenim alanıma dön</a>
<h1>{{ $activity->title }}</h1>
@if($activity->description)<p class="muted">{{ $activity->description }}</p>@endif
@if($activity->path && $activity->type === 'video')
<video id="content-player" controls style="width:100%;border-radius:8px" src="{{ asset('storage/'.$activity->path) }}"></video>
@elseif($activity->path && in_array($activity->type, ['document', 'presentation']))
<iframe src="{{ asset('storage/'.$activity->path) }}" style="width:100%;height:70vh;border:1px solid #eaecf0;border-radius:8px"></iframe>
@elseif($activity->path)
<a class="button secondary" href="{{ asset('storage/'.$activity->path) }}" target="_blank" rel="noopener">Kaynağı aç</a>
@else
<p class="muted">Bu etkinlik için ayrı bir dosya bulunmuyor.</p>
@endif
<div style="margin-top:20px">
<p class="muted" id="progress-label">Görüntülenme süresi: <span id="watched">{{ $progress->watched_seconds }}</span> / {{ $required }} sn</p>
<progress id="progress-bar" max="{{ $required }}" value="{{ min($progress->watched_seconds, $required) }}" style="width:100%"></progress>
</div>
@if($progress->completed)
<p class="alert success">✓ Bu etkinliği tamamladınız.</p>
@else
<form method="post" action="{{ route('activities.complete', $activity) }}" id="complete-form">@csrf<button class="button" type="submit" id="complete-btn" disabled>Tamamlandı olarak işaretle</button></form>
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
        fetch('{{ route('activities.heartbeat', $activity) }}', {
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

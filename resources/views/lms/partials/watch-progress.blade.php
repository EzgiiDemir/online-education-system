{{-- Expects: $progress, $required, $heartbeatRoute, $labelWatched, $durationRoute --}}
@php($en = session('locale', 'tr') === 'en')
<div class="viewer-progress">
    <div class="viewer-progress-label"><span>{{ $labelWatched }}</span><span id="watched-pct">%{{ $required ? min(100, (int) round($progress->watched_seconds / $required * 100)) : 0 }}</span></div>
    <progress id="progress-bar" max="{{ $required }}" value="{{ min($progress->watched_seconds, $required) }}"></progress>
    <div class="progress-breakdown">
        <span><b>{{ $en ? 'Required:' : 'Gerekli:' }}</b> <span id="req-time">{{ gmdate('i:s', $required) }}</span></span>
        <span><b>{{ $en ? 'Verified:' : 'Doğrulanan:' }}</b> <span id="watched-time">{{ gmdate('i:s', min($progress->watched_seconds, $required)) }}</span></span>
        <span><b>{{ $en ? 'Remaining:' : 'Kalan:' }}</b> <span id="remaining-time">{{ gmdate('i:s', max(0, $required - $progress->watched_seconds)) }}</span></span>
    </div>
    <small id="hint" class="muted">
    @if($progress->completed)
    <x-lucide name="check" size="14" /> {{ $en ? 'Completed' : 'Tamamlandı' }}
    @else
    {{ $en ? 'Time spent on a background tab does not count — the content must stay open and visible on screen.' : 'Sekme arka plandayken geçen süre sayılmaz — içerik ekranda açık ve görünür olmalı.' }}
    @endif
    </small>
</div>
@if(!$progress->completed)
<script>
(function(){
    var required = {{ $required }};
    var btn = document.getElementById('complete-btn');
    var pctEl = document.getElementById('watched-pct');
    var barEl = document.getElementById('progress-bar');
    var watchedTimeEl = document.getElementById('watched-time');
    var remainingTimeEl = document.getElementById('remaining-time');
    var token = document.querySelector('meta[name="csrf-token"]').content;
    function fmt(seconds){
        seconds = Math.max(0, Math.round(seconds));
        var m = Math.floor(seconds / 60), s = seconds % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function tick(){
        if (document.visibilityState !== 'visible') return;
        fetch('{{ $heartbeatRoute }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}
        }).then(function(r){ return r.json(); }).then(function(data){
            var watched = Math.min(data.watched_seconds, required);
            pctEl.textContent = '%' + Math.min(100, Math.round(data.watched_seconds / required * 100));
            barEl.value = watched;
            watchedTimeEl.textContent = fmt(watched);
            remainingTimeEl.textContent = fmt(required - watched);
            if (data.eligible && btn) btn.disabled = false;
        });
    }
    setInterval(tick, 5000);
    tick();

    // The required-time gate only means anything if it's based on the
    // video's real length. Read it once from the file itself instead of
    // relying on anyone to have entered it by hand; the server only accepts
    // this the first time (see setMaterialDuration/setActivityDuration), so
    // a learner can't shrink an already-known duration by re-reporting one.
    var player = document.getElementById('content-player');
    if (player) {
        player.addEventListener('loadedmetadata', function () {
            if (!isFinite(player.duration) || player.duration <= 0) return;
            fetch('{{ $durationRoute }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({seconds: Math.round(player.duration)})
            });
        });
    }
})();
</script>
@endif

@extends('lms.layout')
@section('title', $activity->title.' · Arucad Campus LMS')
@section('content')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}"><x-lucide name="arrow-left" size="14" /> Öğrenim alanıma dön</a>
<div class="card surface viewer-card">
<span class="eyebrow">{{ $activity->section->course->title }}</span>
<h1>{{ $activity->title }}</h1>
@if($activity->description)<p class="muted">{{ $activity->description }}</p>@endif
@if($activity->path && $activity->type === 'video')
<div class="viewer-media"><video id="content-player" controls src="{{ asset('storage/'.$activity->path) }}"></video></div>
@elseif($activity->path && in_array($activity->type, ['document', 'presentation']))
<div class="viewer-media"><iframe src="{{ asset('storage/'.$activity->path) }}"></iframe></div>
@elseif($activity->path)
<div class="viewer-empty"><a class="button secondary" href="{{ asset('storage/'.$activity->path) }}" target="_blank" rel="noopener">Kaynağı aç</a></div>
@else
<div class="viewer-empty"><p class="muted" style="margin:0">Bu etkinlik için ayrı bir dosya bulunmuyor.</p></div>
@endif
@include('lms.partials.watch-progress', ['progress' => $progress, 'required' => $required, 'heartbeatRoute' => route('activities.heartbeat', $activity), 'labelWatched' => 'Görüntülenme süresi'])
@if($progress->completed)
<div class="viewer-done"><span class="status" style="background:#eef1e9;color:var(--success-dark)"><x-lucide name="check" size="14" /> Tamamlandı</span></div>
@else
<div class="viewer-actions">
<form method="post" action="{{ route('activities.complete', $activity) }}" id="complete-form">@csrf<button type="submit" id="complete-btn" disabled>Tamamlandı olarak işaretle</button></form>
<small class="muted">İçeriği görüntüledikçe süre otomatik birikir; yeterli süreye ulaşınca buton aktif olur.</small>
</div>
@endif
</div>
</div>
@endsection

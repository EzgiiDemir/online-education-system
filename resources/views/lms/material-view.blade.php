@extends('lms.layout')
@section('title', $material->title.' · '.(session('locale', 'tr') === 'en' ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi'))
@section('content')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}"><x-lucide name="arrow-left" size="14" /> Öğrenim alanıma dön</a>
<div class="card surface viewer-card">
<span class="eyebrow">{{ $material->session->course->title }}</span>
<h1>{{ $material->title }}</h1>
@if(str_starts_with($material->type, 'video/'))
<div class="viewer-media"><video id="content-player" controls src="{{ asset('storage/'.$material->path) }}"></video></div>
@elseif($material->type === 'application/pdf')
<div class="viewer-media"><iframe src="{{ asset('storage/'.$material->path) }}"></iframe></div>
@else
<div class="viewer-empty"><p class="muted">Bu içerik türü tarayıcıda önizlenemiyor.</p><a class="btn btn-outline-secondary" href="{{ asset('storage/'.$material->path) }}" target="_blank" rel="noopener">Dosyayı indir / aç</a></div>
@endif
@if($isVideo)
@include('lms.partials.watch-progress', ['progress' => $progress, 'required' => $required, 'heartbeatRoute' => route('materials.heartbeat', $material), 'durationRoute' => route('materials.duration', $material), 'labelWatched' => 'İzlenme süresi'])
@else
@include('lms.partials.document-review', ['estimatedMinutes' => $estimatedMinutes])
@endif
@if($progress->completed)
<div class="viewer-done"><span class="badge" style="background:#eef1e9;color:var(--success-dark)"><x-lucide name="check" size="14" /> Tamamlandı</span></div>
@else
<div class="viewer-actions">
<form method="post" action="{{ route('materials.complete', $material) }}" id="complete-form">@csrf<button type="submit" id="complete-btn" class="btn btn-primary" @if($isVideo) disabled @endif>{{ $isVideo ? 'Tamamlandı olarak işaretle' : 'Okudum / İnceledim' }}</button></form>
@if($isVideo)<small class="muted">İçeriği görüntüledikçe süre otomatik birikir; yeterli süreye ulaşınca buton aktif olur.</small>@endif
</div>
@endif
</div>
</div>
@endsection

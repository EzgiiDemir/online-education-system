@extends('lms.layout')
@section('title', $material->title.' · '.(session('locale', 'tr') === 'en' ? 'Arucad Training Center' : 'Arucad Eğitim Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}"><x-lucide name="arrow-left" size="14" /> {{ $en ? 'Back to My Learning' : 'Öğrenim alanıma dön' }}</a>
<div class="card surface viewer-card">
<span class="eyebrow">{{ $material->session->course->title }}</span>
<h1>{{ $material->title }}</h1>
@if(str_starts_with($material->type, 'video/'))
<div class="viewer-media"><video id="content-player" controls src="{{ asset('storage/'.$material->path) }}"></video></div>
@elseif($material->type === 'application/pdf')
<div class="viewer-media"><iframe src="{{ asset('storage/'.$material->path) }}"></iframe></div>
@else
<div class="viewer-empty"><p class="muted">{{ $en ? 'This content type cannot be previewed in the browser.' : 'Bu içerik türü tarayıcıda önizlenemiyor.' }}</p><a class="btn btn-outline-secondary" href="{{ asset('storage/'.$material->path) }}" target="_blank" rel="noopener">{{ $en ? 'Download / open file' : 'Dosyayı indir / aç' }}</a></div>
@endif
@if($isVideo)
@include('lms.partials.watch-progress', ['progress' => $progress, 'required' => $required, 'heartbeatRoute' => route('materials.heartbeat', $material), 'durationRoute' => route('materials.duration', $material), 'labelWatched' => $en ? 'Watch time' : 'İzlenme süresi'])
@else
@include('lms.partials.document-review', ['estimatedMinutes' => $estimatedMinutes])
@endif
@if($progress->completed)
<div class="viewer-done"><span class="badge" style="background:#eef1e9;color:var(--success-dark)"><x-lucide name="check" size="14" /> {{ $en ? 'Completed' : 'Tamamlandı' }}</span></div>
@else
<div class="viewer-actions">
<form method="post" action="{{ route('materials.complete', $material) }}" id="complete-form">@csrf<button type="submit" id="complete-btn" class="btn btn-primary" @if($isVideo) disabled @endif>{{ $isVideo ? ($en ? 'Mark as completed' : 'Tamamlandı olarak işaretle') : ($en ? 'I have read / reviewed it' : 'Okudum / İnceledim') }}</button></form>
@if($isVideo)<small class="muted">{{ $en ? 'Time accrues automatically while you view the content; the button activates once you reach the required time.' : 'İçeriği görüntüledikçe süre otomatik birikir; yeterli süreye ulaşınca buton aktif olur.' }}</small>@endif
</div>
@endif
</div>
</div>
@endsection

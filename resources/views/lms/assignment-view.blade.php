@extends('lms.layout')
@section('title', $activity->title.' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}">← {{ $en ? 'Back to My Learning' : 'Öğrenim alanıma dön' }}</a>
<div class="card surface viewer-card">
<span class="eyebrow">{{ $activity->section->course->title }}</span>
<h1>{{ $activity->title }}</h1>
@if($activity->description)<p class="muted">{{ $activity->description }}</p>@endif

@if($submissions->isNotEmpty())
<div class="quiz-attempt-history">
<p class="muted" style="margin-bottom:8px">{{ $en ? 'Your submissions' : 'Teslimleriniz' }}</p>
@foreach($submissions as $submission)
@php($passed = $submission->score >= ($activity->pass_score ?? 0))
<div class="requirement-row" style="align-items:flex-start">
<div>
<span>{{ $submission->submitted_at->format('d.m.Y H:i') }}</span>
@if($submission->isGraded())
<br><small class="{{ $passed ? 'muted' : 'due-warning is-overdue' }}">{{ $submission->feedback ?: ($en ? 'No feedback provided.' : 'Geri bildirim eklenmedi.') }}</small>
@else
<br><small class="muted">{{ $en ? 'Awaiting review' : 'Değerlendirme bekliyor' }}</small>
@endif
</div>
@if($submission->isGraded())
<span class="status" style="{{ $passed ? 'background:#eef1e9;color:var(--success-dark)' : 'background:#fbe9e4;color:#b3402e' }}">{{ $passed ? '✓' : '✕' }} %{{ $submission->score }}</span>
@endif
</div>
@endforeach
</div>
@endif

@if($hasPassed)
<div class="viewer-done"><span class="status" style="background:#eef1e9;color:var(--success-dark)">✓ {{ $en ? 'Assignment accepted' : 'Ödev kabul edildi' }}</span></div>
@elseif($hasPendingSubmission)
<div class="viewer-empty"><p class="muted" style="margin:0">{{ $en ? 'Your submission is awaiting review by your instructor.' : 'Teslim ettiğiniz ödev eğitmeniniz tarafından değerlendirilmeyi bekliyor.' }}</p></div>
@else
<form method="post" action="{{ route('activities.submissions.store', $activity) }}" enctype="multipart/form-data" class="quiz-form">
@csrf
@if($activity->submission_type === 'file')
<label>{{ $en ? 'File' : 'Dosya' }}<input type="file" name="file" required></label>
@elseif($activity->submission_type === 'url')
<label>{{ $en ? 'Link (URL)' : 'Bağlantı (URL)' }}<input type="url" name="content" placeholder="https://…" required></label>
@else
<label>{{ $en ? 'Your answer' : 'Cevabınız' }}<textarea name="content" rows="8" required></textarea></label>
@endif
<button type="submit" style="margin-top:14px">{{ $en ? 'Submit' : 'Teslim et' }}</button>
</form>
@endif
</div>
</div>
@endsection

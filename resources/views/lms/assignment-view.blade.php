@extends('lms.layout')
@section('title', $activity->title.' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}"><x-lucide name="arrow-left" size="14" /> {{ $en ? 'Back to My Learning' : 'Öğrenim alanıma dön' }}</a>
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
<span class="status" style="{{ $passed ? 'background:#eef1e9;color:var(--success-dark)' : 'background:#fbe9e4;color:#b3402e' }}"><x-lucide :name="$passed ? 'check' : 'x'" size="14" /> %{{ $submission->score }}</span>
@endif
</div>
@endforeach
</div>
@endif

@if($hasPassed)
<div class="viewer-done"><span class="status" style="background:#eef1e9;color:var(--success-dark)"><x-lucide name="check" size="14" /> {{ $en ? 'Assignment accepted' : 'Ödev kabul edildi' }}</span></div>
@elseif($hasPendingSubmission)
<div class="viewer-empty"><p class="muted" style="margin:0">{{ $en ? 'Your submission is awaiting review by your instructor.' : 'Teslim ettiğiniz ödev eğitmeniniz tarafından değerlendirilmeyi bekliyor.' }}</p></div>
@else
<form method="post" action="{{ route('activities.submissions.store', $activity) }}" enctype="multipart/form-data" class="quiz-form">
@csrf
@if($activity->submission_type === 'file')
<label class="form-label">{{ $en ? 'File' : 'Dosya' }}</label><input type="file" name="file" class="form-control" required>
@elseif($activity->submission_type === 'url')
<label class="form-label">{{ $en ? 'Link (URL)' : 'Bağlantı (URL)' }}</label><input type="url" name="content" class="form-control" placeholder="https://…" required>
@else
<label class="form-label">{{ $en ? 'Your answer' : 'Cevabınız' }}</label><textarea name="content" class="form-control" rows="8" required></textarea>
@endif
<button type="submit" class="btn btn-primary mt-3">{{ $en ? 'Submit' : 'Teslim et' }}</button>
</form>
@endif
</div>
</div>
@endsection

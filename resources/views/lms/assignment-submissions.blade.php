@extends('lms.instructor-layout')
@section('page-title', $activity->title.' · Teslimler')
@section('content')
<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $course->title }}</span><h2>{{ $activity->title }} — Teslimler</h2></div></div>
<div class="card">
@forelse($submissions as $submission)
<div style="border-top:1px solid #eaecf0;padding:20px 0" @if($loop->first) style="border-top:0;padding-top:0" @endif>
<div class="course-card-top"><strong>{{ $submission->enrollment->user->name }}</strong><span class="muted">{{ $submission->submitted_at->format('d.m.Y H:i') }}</span></div>
<div style="margin:10px 0">
@if($activity->submission_type === 'file' && $submission->file_path)
<a class="button secondary" href="{{ asset('storage/'.$submission->file_path) }}" target="_blank" rel="noopener">Dosyayı indir</a>
@elseif($activity->submission_type === 'url')
<a href="{{ $submission->content }}" target="_blank" rel="noopener">{{ $submission->content }}</a>
@else
<p style="white-space:pre-wrap;background:#f7f8fa;padding:14px;border-radius:8px;margin:0">{{ $submission->content }}</p>
@endif
</div>
@if($submission->isGraded())
<p class="muted">Değerlendirildi: %{{ $submission->score }} · {{ $submission->grader->name }} — {{ $submission->graded_at->format('d.m.Y H:i') }}</p>
@if($submission->feedback)<p class="muted"><b>Geri bildirim:</b> {{ $submission->feedback }}</p>@endif
@else
<form method="post" action="{{ route('instructor.submissions.grade', $submission) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
@csrf
<div class="field" style="margin:0"><label>Puan (0-100)</label><input type="number" name="score" min="0" max="100" required style="width:100px"></div>
<div class="field" style="margin:0;flex:1;min-width:200px"><label>Geri bildirim</label><input type="text" name="feedback"></div>
<button type="submit">Değerlendir</button>
</form>
@endif
</div>
@empty
<p class="muted" style="margin:0">Bu etkinlik için henüz teslim yapılmadı.</p>
@endforelse
</div>
@endsection

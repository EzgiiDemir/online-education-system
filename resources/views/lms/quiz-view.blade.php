@extends('lms.layout')
@section('title', $activity->title.' · Arucad Campus LMS')
@section('content')
<div class="viewer-wrap">
<a class="viewer-back" href="{{ route('learning.index') }}">← Öğrenim alanıma dön</a>
<div class="card surface viewer-card">
<span class="eyebrow">{{ $activity->section->course->title }}</span>
<h1>{{ $activity->title }}</h1>
@if($activity->description)<p class="muted">{{ $activity->description }}</p>@endif

<div class="quiz-meta">
<span>{{ count($activity->quizQuestions) }} soru</span>
@if($activity->pass_score)<span>· Geçme notu: %{{ $activity->pass_score }}</span>@endif
@if($activity->max_attempts)<span>· Deneme hakkı: {{ $activity->max_attempts }}</span>@endif
</div>

@if($attempts->isNotEmpty())
<div class="quiz-attempt-history">
<p class="muted" style="margin-bottom:8px">Önceki denemeleriniz</p>
@foreach($attempts as $attempt)
<div class="requirement-row"><span>{{ $attempt->submitted_at->format('d.m.Y H:i') }}</span><span class="status" style="{{ $attempt->passed ? 'background:#eef1e9;color:var(--success-dark)' : 'background:#fbe9e4;color:#b3402e' }}">{{ $attempt->passed ? '✓' : '✕' }} %{{ $attempt->score }}</span></div>
@endforeach
</div>
@endif

@if($hasPassed)
<div class="viewer-done"><span class="status" style="background:#eef1e9;color:var(--success-dark)">✓ Sınavı geçtiniz</span></div>
@elseif(!$canAttempt)
<div class="viewer-empty"><p class="muted" style="margin:0">İzin verilen deneme hakkınızı kullandınız. Yeniden deneme için eğitmeninizle iletişime geçin.</p></div>
@else
<form method="post" action="{{ route('activities.quiz-attempts.store', $activity) }}" class="quiz-form">
@csrf
@foreach($activity->quizQuestions as $index => $question)
<fieldset class="quiz-question">
<legend>{{ $index + 1 }}. {{ $question->question }}</legend>
@foreach($question->options as $option)
<label class="quiz-option">
<input type="{{ $question->type === 'multi_choice' ? 'checkbox' : 'radio' }}" name="answers[{{ $question->id }}]{{ $question->type === 'multi_choice' ? '[]' : '' }}" value="{{ $option->id }}"{{ $question->type === 'multi_choice' ? '' : ' required' }}>
{{ $option->label }}
</label>
@endforeach
</fieldset>
@endforeach
@if($attemptsRemaining !== null)<p class="muted">Kalan deneme hakkı: {{ $attemptsRemaining }}</p>@endif
<button type="submit">Sınavı gönder</button>
</form>
@endif
</div>
</div>
@endsection

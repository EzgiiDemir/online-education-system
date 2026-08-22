@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Learning paths' : 'Öğrenim yolları').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'LEARNING PATHS' : 'ÖĞRENİM YOLLARI' }}</span><h1>{{ $en ? 'Learning paths' : 'Öğrenim yolları' }}</h1><p class="muted">{{ $en ? 'Structured, multi-course programs that build a full skill set.' : 'Tam bir yetkinlik seti kazandıran, birden çok eğitimden oluşan yapılandırılmış programlar.' }}</p></div></div>
<div class="course-grid">
@forelse($paths as $path)
<article class="course-card surface">
<div class="course-card-top"><span class="status">🗺 {{ $path->courses_count }} {{ $en ? 'courses' : 'eğitim' }}</span></div>
<h2><a href="{{ route('learning-paths.show', $path) }}">{{ $path->title }}</a></h2>
<p class="muted">{{ $path->description }}</p>
@auth
<div class="meter"><span style="width:{{ $progress[$path->id] ?? 0 }}%"></span></div>
<small class="muted">%{{ $progress[$path->id] ?? 0 }} {{ $en ? 'complete' : 'tamamlandı' }}</small>
@endauth
<a class="button" href="{{ route('learning-paths.show', $path) }}">{{ $en ? 'View path' : 'Yolu görüntüle' }} <span>→</span></a>
</article>
@empty
<div class="empty-state surface">{{ $en ? 'No learning paths published yet.' : 'Henüz yayınlanmış bir öğrenim yolu yok.' }}</div>
@endforelse
</div>
@endsection

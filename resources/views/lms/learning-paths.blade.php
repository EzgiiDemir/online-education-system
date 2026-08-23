@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Learning paths' : 'Öğrenim yolları').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'LEARNING PATHS' : 'ÖĞRENİM YOLLARI' }}</span><h1>{{ $en ? 'Learning paths' : 'Öğrenim yolları' }}</h1><p class="muted">{{ $en ? 'Structured, multi-course programs that build a full skill set.' : 'Tam bir yetkinlik seti kazandıran, birden çok eğitimden oluşan yapılandırılmış programlar.' }}</p></div></div>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
@forelse($paths as $path)
<article class="course-card surface" style="--course-accent:var(--course-accent-{{ $path->id % 6 }});border-left:4px solid var(--course-accent)">
<div class="course-card-top"><span class="badge"><x-lucide name="map" size="14" /> {{ $path->courses_count }} {{ $en ? 'courses' : 'eğitim' }}</span></div>
<h2><a href="{{ route('learning-paths.show', $path) }}">{{ $path->title }}</a></h2>
<p class="muted">{{ $path->description }}</p>
@auth
<div class="meter"><span style="width:{{ $progress[$path->id] ?? 0 }}%"></span></div>
<small class="muted">%{{ $progress[$path->id] ?? 0 }} {{ $en ? 'complete' : 'tamamlandı' }}</small>
@endauth
<a class="btn btn-primary" href="{{ route('learning-paths.show', $path) }}">{{ $en ? 'View path' : 'Yolu görüntüle' }} <x-lucide name="arrow-right" size="14" /></a>
</article>
@empty
<div class="empty-state surface">{{ $en ? 'No learning paths published yet.' : 'Henüz yayınlanmış bir öğrenim yolu yok.' }}</div>
@endforelse
</div>
@endsection

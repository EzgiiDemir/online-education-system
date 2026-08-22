@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'My Skills' : 'Yetkinliklerim').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'MY SKILLS' : 'YETKİNLİKLERİM' }}</span><h1>{{ $en ? 'Skills' : 'Yetkinliklerim' }}</h1><p class="muted">{{ $en ? 'Skills you have earned by completing courses.' : 'Eğitimleri tamamlayarak kazandığınız yetkinlikler.' }}</p></div></div>
<div class="course-grid">
@forelse($skills as $skill)
<article class="course-card surface">
<div class="course-card-top"><span class="status">🏅 {{ $en ? 'SKILL' : 'YETKİNLİK' }}</span></div>
<h2>{{ $skill->name }}</h2>
<p class="muted">{{ $en ? 'Earned from:' : 'Kazanıldığı eğitim(ler):' }}</p>
<ul class="curriculum-list">
@foreach($skill->courses as $course)
<li><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></li>
@endforeach
</ul>
</article>
@empty
<div class="empty-state surface">{{ $en ? "You haven't earned any skills yet. Complete a course to start building your skill profile." : 'Henüz bir yetkinlik kazanmadınız. Yetkinlik profilinizi oluşturmak için bir eğitimi tamamlayın.' }} <a href="{{ route('lms.home') }}">{{ $en ? 'Browse courses →' : 'Kataloğu görüntüleyin →' }}</a></div>
@endforelse
</div>
@endsection

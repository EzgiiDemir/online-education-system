@extends('lms.layout')
@section('title', 'Arucad Campus LMS · Eğitim kataloğu')
@section('content')
@php($en = session('locale', 'tr') === 'en')
@guest
<section class="hero"><div><h1>{{ $en ? 'Learn, grow,' : 'Öğren, geliş,' }}<br><em>{{ $en ? 'track it all.' : 'takip et.' }}</em></h1><p>{{ $en ? 'Discover courses, join live classes, and track your growth from one platform.' : 'Eğitimleri keşfedin, canlı sınıflara katılın ve gelişiminizi tek bir platformdan takip edin.' }}</p></div><div class="hero-mark"><img src="{{ asset('images/sdg/onlinelogo.jpg') }}" alt="Arucad Campus LMS"></div></section>
@endguest
@auth
@php($hour = (int) now()->format('H'))
@php($greeting = $hour < 12 ? ($en ? 'Good morning' : 'Günaydın') : ($hour < 18 ? ($en ? 'Good afternoon' : 'İyi günler') : ($en ? 'Good evening' : 'İyi akşamlar')))
@php($pendingCount = $requiredIncomplete->count() + $todaySessions->count())
<section class="home-greeting surface">
    <div class="greeting-text">
        <span class="eyebrow">{{ strtoupper($greeting) }}</span>
        <h1>{{ auth()->user()->name }}</h1>
        <p>
        @if($pendingCount > 0)
        {{ $en ? 'You have '.$pendingCount.' learning action'.($pendingCount > 1 ? 's' : '').' today.' : 'Bugün için '.$pendingCount.' öğrenme göreviniz var.' }}
        @else
        {{ $en ? "You're all caught up. Nice work." : 'Her şey güncel, harika iş çıkardınız.' }}
        @endif
        </p>
    </div>
    @if($continueLearning)
    <div class="continue-card">
        <small class="muted">{{ $en ? 'Continue' : 'Devam et' }}</small>
        <strong>{{ $continueLearning->course->title }}</strong>
        <div class="meter"><span style="width:{{ $continueLearning->completionStats()['percent'] }}%"></span></div>
        <a class="button" href="{{ $continueUrl }}">{{ $en ? 'Continue learning' : 'Öğrenmeye devam et' }} <span>→</span></a>
    </div>
    @endif
</section>
@if($todaySessions->isNotEmpty())
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'TODAY' : 'BUGÜN' }}</span><h2>{{ $en ? "Today's live sessions" : 'Bugünün canlı dersleri' }}</h2></div></div>
<div class="today-grid">@foreach($todaySessions as $session)<div class="today-card surface"><span class="status">{{ $en ? 'LIVE CLASS' : 'CANLI DERS' }}</span><h3>{{ $session->title }}</h3><p class="muted">{{ $session->course->title }} · {{ $session->starts_at?->format('H:i') }}</p><a class="button" href="{{ route('sessions.classroom', $session) }}">{{ $en ? 'Join class' : 'Derse katıl' }} <span>→</span></a></div>@endforeach</div>
@endif
@if($requiredIncomplete->isNotEmpty())
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'REQUIRED' : 'ZORUNLU' }}</span><h2>{{ $en ? 'Required training' : 'Zorunlu eğitimler' }}</h2></div></div>
<div class="today-grid">@foreach($requiredIncomplete as $row)<div class="today-card surface"><span class="status">{{ $row['stats']['percent'] }}%</span><h3>{{ $row['enrollment']->course->title }}</h3><div class="meter"><span style="width:{{ $row['stats']['percent'] }}%"></span></div><small class="muted">{{ $row['stats']['completed'] }} / {{ $row['stats']['total'] }} {{ $en ? 'completed' : 'tamamlandı' }}</small></div>@endforeach</div>
@endif
@endauth
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'CATALOG' : 'KATALOG' }}</span><h2>{{ $en ? 'Upcoming courses' : 'Yaklaşan eğitimler' }}</h2></div><span class="muted">{{ $courses->total() }} {{ $en ? 'published courses' : 'yayınlanmış eğitim' }}</span></div>
<div class="course-grid">@forelse($courses as $course)<article class="course-card surface"><div class="course-card-top"><span class="status">{{ $course->delivery_type === 'online' ? ($en ? 'LIVE CLASS' : 'CANLI SINIF') : ($en ? 'SELF-PACED' : 'İÇERİK TABANLI') }}</span><span class="muted">{{ strtoupper($course->language ?? 'tr') }}</span></div><h3><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h3><p class="muted">{{ $course->description }}</p><div class="course-meta"><span>◉ {{ $course->instructor->name }}</span><span>•</span><span>{{ $course->sessions->count() }} {{ $en ? 'sessions' : 'seans' }}</span></div>@auth @if(!$enrolledCourseIds->contains($course->id))<form method="post" action="{{ route('courses.enroll',$course) }}">@csrf<button type="submit">{{ $en ? 'Enroll now' : 'Eğitime kayıt ol' }} <span>→</span></button></form>@else<span class="completed-label">✓ {{ $en ? 'Enrolled' : 'Kayıtlısınız' }}</span>@endif @else<a class="button" href="{{ route('login') }}">{{ $en ? 'Sign in to enroll' : 'Kayıt olmak için giriş yapın' }} <span>→</span></a>@endauth</article>@empty<div class="empty-state surface">{{ $en ? 'No published courses yet.' : 'Henüz yayınlanmış eğitim yok.' }}</div>@endforelse</div>
{{ $courses->links('lms.partials.pagination') }}
@endsection

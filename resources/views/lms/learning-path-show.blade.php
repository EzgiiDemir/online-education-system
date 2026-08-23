@extends('lms.layout')
@section('title', $path->title.' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="course-show">
    <div class="course-show-hero card surface" style="--course-accent:var(--course-accent-{{ $path->id % 6 }});border-top:4px solid var(--course-accent)">
        <div class="course-show-hero-body">
            <span class="status"><x-lucide name="map" size="14" /> {{ $en ? 'LEARNING PATH' : 'ÖĞRENİM YOLU' }}</span>
            <h1>{{ $path->title }}</h1>
            <p class="muted">{{ $path->description }}</p>
            @auth
            <form method="post" action="{{ route('learning-paths.enroll', $path) }}">@csrf<button type="submit" class="btn btn-primary">{{ $en ? 'Enroll in all courses' : 'Tüm eğitimlere kayıt ol' }} <x-lucide name="arrow-right" size="14" /></button></form>
            @else
            <a class="btn btn-primary" href="{{ route('login') }}">{{ $en ? 'Sign in to enroll' : 'Kayıt olmak için giriş yapın' }} <x-lucide name="arrow-right" size="14" /></a>
            @endauth
        </div>
    </div>

    <div class="card surface course-show-section">
        <h2>{{ $en ? 'Courses in this path' : 'Bu yoldaki eğitimler' }}</h2>
        <ol class="path-course-list">
        @foreach($path->courses as $course)
        @php($locked = auth()->check() && $path->isLockedFor($course, auth()->user()))
        <li class="path-course-item{{ $locked ? ' is-locked' : '' }}">
            <div>
                <strong>{{ $loop->iteration }}. {{ $course->title }}</strong>
                @unless($course->pivot->is_required)<small class="muted">({{ $en ? 'optional' : 'opsiyonel' }})</small>@endunless
            </div>
            @if($locked)
            <span class="status"><x-lucide name="lock" size="14" /> {{ $en ? 'Locked' : 'Kilitli' }}</span>
            @elseif($completedCourseIds->contains($course->id))
            <span class="status"><x-lucide name="check" size="14" /> {{ $en ? 'Completed' : 'Tamamlandı' }}</span>
            @elseif($enrolledCourseIds->contains($course->id))
            <a class="btn btn-outline-secondary" href="{{ route('courses.show', $course) }}">{{ $en ? 'Continue' : 'Devam et' }} <x-lucide name="arrow-right" size="14" /></a>
            @else
            <a class="btn btn-outline-secondary" href="{{ route('courses.show', $course) }}">{{ $en ? 'View' : 'Görüntüle' }} <x-lucide name="arrow-right" size="14" /></a>
            @endif
        </li>
        @endforeach
        </ol>
    </div>
</div>
@endsection

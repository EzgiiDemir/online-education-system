@extends('lms.layout')
@section('title', $path->title.' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="course-show">
    <div class="course-show-hero card surface">
        <div class="course-show-hero-body">
            <span class="status">🗺 {{ $en ? 'LEARNING PATH' : 'ÖĞRENİM YOLU' }}</span>
            <h1>{{ $path->title }}</h1>
            <p class="muted">{{ $path->description }}</p>
            @auth
            <form method="post" action="{{ route('learning-paths.enroll', $path) }}">@csrf<button type="submit">{{ $en ? 'Enroll in all courses' : 'Tüm eğitimlere kayıt ol' }} <span>→</span></button></form>
            @else
            <a class="button" href="{{ route('login') }}">{{ $en ? 'Sign in to enroll' : 'Kayıt olmak için giriş yapın' }} <span>→</span></a>
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
            <span class="status">🔒 {{ $en ? 'Locked' : 'Kilitli' }}</span>
            @elseif($completedCourseIds->contains($course->id))
            <span class="status">✓ {{ $en ? 'Completed' : 'Tamamlandı' }}</span>
            @elseif($enrolledCourseIds->contains($course->id))
            <a class="button secondary" href="{{ route('courses.show', $course) }}">{{ $en ? 'Continue' : 'Devam et' }} →</a>
            @else
            <a class="button secondary" href="{{ route('courses.show', $course) }}">{{ $en ? 'View' : 'Görüntüle' }} →</a>
            @endif
        </li>
        @endforeach
        </ol>
    </div>
</div>
@endsection

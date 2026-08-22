@extends('lms.layout')
@section('title', $course->title.' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="course-show">
    @if($newerVersion)
    <div class="alert success" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
    <span>{{ $en ? 'A newer version of this course is available (v'.$newerVersion->version.').' : 'Bu eğitimin daha yeni bir sürümü mevcut (v'.$newerVersion->version.').' }}</span>
    <a class="button secondary" href="{{ route('courses.show', $newerVersion) }}">{{ $en ? 'View v'.$newerVersion->version : 'v'.$newerVersion->version.'\'i görüntüle' }} →</a>
    </div>
    @endif
    <div class="course-show-hero card surface">
        @if($course->poster_path || $course->image_path)
        <div class="course-show-cover"><img src="{{ asset('storage/'.($course->poster_path ?: $course->image_path)) }}" alt=""></div>
        @endif
        <div class="course-show-hero-body">
            <span class="status">{{ $course->delivery_type === 'online' ? ($en ? 'LIVE CLASS' : 'CANLI SINIF') : ($en ? 'SELF-PACED' : 'İÇERİK TABANLI') }}</span>
            <h1>{{ $course->title }}</h1>
            <div class="course-show-meta">
                <span>◉ {{ $course->instructor->name }}</span>
                <span>•</span>
                <span>{{ strtoupper($course->language ?? 'tr') }}</span>
                <span>•</span>
                <span>{{ $course->sessions->count() }} {{ $en ? 'sessions' : 'seans' }}</span>
                <span>•</span>
                <span>v{{ $course->version }}</span>
                @if($course->certificate_enabled)<span>•</span><span>🎓 {{ $en ? 'Certificate' : 'Sertifikalı' }}</span>@endif
                @if($course->category && $course->category !== 'optional')<span>•</span><span class="status">{{ ['required' => $en ? 'REQUIRED' : 'ZORUNLU', 'compliance' => $en ? 'COMPLIANCE' : 'UYUMLULUK', 'onboarding' => $en ? 'ONBOARDING' : 'ORYANTASYON', 'professional_development' => $en ? 'PROF. DEVELOPMENT' : 'MESLEKİ GELİŞİM'][$course->category] ?? $course->category }}</span>@endif
            </div>

            @if($enrollment)
            <div class="course-show-progress">
                <div class="meter"><span style="width:{{ $stats['percent'] }}%"></span></div>
                <small class="muted">{{ $stats['completed'] }} / {{ $stats['total'] }} {{ $en ? 'completed' : 'tamamlandı' }} · %{{ $stats['percent'] }}</small>
                @if($enrollment->status === 'completed' && $enrollment->course_version)
                <p class="due-warning">{{ $en ? 'Completed against course version v'.$enrollment->course_version.'.' : 'v'.$enrollment->course_version.' sürümüne göre tamamlandı.' }}</p>
                @endif
                @if($enrollment->status !== 'completed' && $course->dueDateFor($enrollment))
                <p class="{{ $course->isOverdueFor($enrollment) ? 'due-warning is-overdue' : 'due-warning' }}">
                @if($course->isOverdueFor($enrollment))
                ⚠ {{ $en ? 'Overdue since '.$course->dueDateFor($enrollment)->format('d.m.Y') : $course->dueDateFor($enrollment)->format('d.m.Y').' tarihinden beri gecikmiş' }}
                @else
                {{ $en ? 'Due by '.$course->dueDateFor($enrollment)->format('d.m.Y') : $course->dueDateFor($enrollment)->format('d.m.Y').' tarihine kadar tamamlanmalı' }}
                @endif
                </p>
                @endif
                @if($enrollment->status === 'completed' && $enrollment->certificate && $enrollment->certificate->expiresAt())
                <p class="{{ $enrollment->certificate->isExpired() ? 'due-warning is-overdue' : 'due-warning' }}">
                {{ $enrollment->certificate->isExpired() ? ($en ? 'Certificate expired on ' : 'Sertifika süresi doldu: ') : ($en ? 'Certificate valid until ' : 'Sertifika geçerlilik tarihi: ') }}{{ $enrollment->certificate->expiresAt()->format('d.m.Y') }}
                </p>
                @endif
            </div>
            @if($enrollment->status === 'completed')
                @if($enrollment->certificate)<a class="button" href="{{ route('certificates.download', $enrollment->certificate) }}">{{ $en ? 'Download certificate' : 'Sertifikayı indir' }} <span>→</span></a> <a class="button secondary" href="{{ route('certificates.verify', $enrollment->certificate->certificate_number) }}">{{ $en ? 'Verify' : 'Doğrula' }}</a>@endif
            @elseif($continueUrl)
                <a class="button" href="{{ $continueUrl }}">{{ $en ? 'Continue learning' : 'Öğrenmeye devam et' }} <span>→</span></a>
            @else
                <a class="button" href="{{ route('learning.index') }}">{{ $en ? 'Go to My Learning' : 'Eğitimlerime git' }} <span>→</span></a>
            @endif
            @else
                @auth
                <form method="post" action="{{ route('courses.enroll', $course) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit">{{ $en ? 'Enroll now' : 'Eğitime kayıt ol' }} <span>→</span></button></form>
                @else
                <a class="button" href="{{ route('login') }}">{{ $en ? 'Sign in to enroll' : 'Kayıt olmak için giriş yapın' }} <span>→</span></a>
                @endauth
            @endif
        </div>
    </div>

    <div class="card surface course-show-section">
        <h2>{{ $en ? 'About' : 'Hakkında' }}</h2>
        <p class="muted">{{ $course->description ?: ($en ? 'No description provided yet.' : 'Henüz açıklama eklenmemiş.') }}</p>
        @if($course->skills->isNotEmpty())
        <p class="muted" style="margin-bottom:6px">{{ $en ? "What you'll learn" : 'Kazanacağınız yetkinlikler' }}</p>
        <div class="skill-tags">@foreach($course->skills as $skill)<span class="skill-tag">🏅 {{ $skill->name }}</span>@endforeach</div>
        @endif
    </div>

    @if($enrollment && $stats['total'] > 0)
    <div class="card surface course-show-section">
        <h2>{{ $en ? 'Completion requirements' : 'Tamamlanma koşulları' }}</h2>
        <p class="muted">{{ $en ? 'You need to complete all required items below to earn your certificate.' : 'Sertifika kazanmak için aşağıdaki tüm zorunlu içerikleri tamamlamanız gerekir.' }}</p>
        <div class="requirement-row"><span>{{ $en ? 'Materials & sessions' : 'Materyal ve seanslar' }}</span><span class="status">{{ $stats['completed'] }} / {{ $stats['total'] }}</span></div>
    </div>
    @endif

    @if($course->sections->isNotEmpty())
    <div class="card surface course-show-section">
        <h2>{{ $en ? 'Curriculum' : 'Müfredat' }}</h2>
        @foreach($course->sections as $section)
        <div class="curriculum-section">
            <strong>{{ $section->title }}</strong>
            <ul class="curriculum-list">
            @foreach($section->activities as $activity)
            <li>
                <span>{{ $activity->title }} @unless($activity->is_required)<small class="muted">({{ $en ? 'optional' : 'opsiyonel' }})</small>@endunless</span>
                @if($enrollment && $activity->type !== 'live_session')
                    @php($activityDone = $enrollment->activityProgress->firstWhere('activity_id', $activity->id)?->completed)
                    <span class="status">{{ $activityDone ? '✓' : ($en ? 'Not started' : 'Başlanmadı') }}</span>
                @endif
            </li>
            @endforeach
            </ul>
        </div>
        @endforeach
    </div>
    @endif

    @if($course->sessions->isNotEmpty())
    <div class="card surface course-show-section">
        <h2>{{ $en ? 'Sessions' : 'Seanslar' }}</h2>
        @foreach($course->sessions as $session)
        <div class="curriculum-section">
            <div class="course-card-top"><strong>{{ $session->title }}</strong><span class="muted">{{ $session->starts_at?->format('d.m.Y H:i') ?? ($en ? 'Date pending' : 'Tarih bekleniyor') }}</span></div>
            @if($session->materials->isNotEmpty())
            <ul class="curriculum-list">
            @foreach($session->materials as $material)
            <li>
                <span>📄 {{ $material->title }}</span>
                @if($enrollment)
                    @php($materialDone = $enrollment->progress->firstWhere('material_id', $material->id)?->completed)
                    <span class="status">{{ $materialDone ? '✓' : ($en ? 'Not started' : 'Başlanmadı') }}</span>
                @endif
            </li>
            @endforeach
            </ul>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

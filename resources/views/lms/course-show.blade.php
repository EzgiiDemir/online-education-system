@extends('lms.layout')
@section('title', $course->title.' · '.(session('locale', 'tr') === 'en' ? 'Arucad Training Center' : 'Arucad Eğitim Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="course-show">
    @if($newerVersion)
    <div class="alert alert-success" style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px">
    <span>{{ $en ? 'A newer version of this course is available (v'.$newerVersion->version.').' : 'Bu eğitimin daha yeni bir sürümü mevcut (v'.$newerVersion->version.').' }}</span>
    <a class="btn btn-outline-secondary" href="{{ route('courses.show', $newerVersion) }}">{{ $en ? 'View v'.$newerVersion->version : 'v'.$newerVersion->version.'\'i görüntüle' }} <x-lucide name="arrow-right" size="14" /></a>
    </div>
    @endif
    <div class="course-show-hero card surface" style="--course-accent:var(--course-accent-{{ $course->id % 6 }})">
        @if($course->poster_path || $course->image_path)
        <div class="course-show-cover"><img src="{{ asset('storage/'.($course->poster_path ?: $course->image_path)) }}" alt=""></div>
        @else
        <div class="course-show-cover course-show-cover-accent"><span class="course-show-cover-initial">{{ mb_strtoupper(mb_substr($course->title, 0, 1)) }}</span></div>
        @endif
        <div class="course-show-hero-body">
            <span class="badge">{{ $course->delivery_type === 'online' ? ($en ? 'LIVE CLASS' : 'CANLI SINIF') : ($en ? 'SELF-PACED' : 'İÇERİK TABANLI') }}</span>
            <h1>{{ $course->title }}</h1>
            <div class="course-show-meta">
                <span><x-lucide name="user" size="14" /> {{ $course->instructor->name }}</span>
                <span>•</span>
                <span>{{ strtoupper($course->language ?? 'tr') }}</span>
                <span>•</span>
                <span>{{ $course->sessions->count() }} {{ $en ? 'sessions' : 'seans' }}</span>
                <span>•</span>
                <span>v{{ $course->version }}</span>
                @if($course->certificate_enabled)<span>•</span><span><x-lucide name="graduation-cap" size="14" /> {{ $en ? 'Certificate' : 'Sertifikalı' }}</span>@endif
                @if($course->category && $course->category !== 'optional')<span>•</span><span class="badge">{{ ['required' => $en ? 'REQUIRED' : 'ZORUNLU', 'compliance' => $en ? 'COMPLIANCE' : 'UYUMLULUK', 'onboarding' => $en ? 'ONBOARDING' : 'ORYANTASYON', 'professional_development' => $en ? 'PROF. DEVELOPMENT' : 'MESLEKİ GELİŞİM'][$course->category] ?? $course->category }}</span>@endif
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
                <x-lucide name="triangle-alert" size="14" /> {{ $en ? 'Overdue since '.$course->dueDateFor($enrollment)->format('d.m.Y') : $course->dueDateFor($enrollment)->format('d.m.Y').' tarihinden beri gecikmiş' }}
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
            @if($needsRecertification)
                <span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Recertification required' : 'Yeniden sertifikalandırma gerekiyor' }}</span>
                @auth
                <form method="post" action="{{ route('courses.enroll', $course) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-primary">{{ $en ? 'Retake course' : 'Eğitimi yeniden al' }} <x-lucide name="arrow-right" size="14" /></button></form>
                @endauth
            @elseif($enrollment->status === 'completed')
                @if($enrollment->certificate?->isRevoked())<span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Certificate revoked' : 'Sertifika iptal edildi' }}</span>
                @elseif($enrollment->certificate)<a class="btn btn-primary" href="{{ route('certificates.download', $enrollment->certificate) }}">{{ $en ? 'Download certificate' : 'Sertifikayı indir' }} <x-lucide name="arrow-right" size="14" /></a> <a class="btn btn-outline-secondary" href="{{ route('certificates.verify', $enrollment->certificate->certificate_number) }}">{{ $en ? 'Verify' : 'Doğrula' }}</a>@endif
            @elseif($continueUrl)
                <a class="btn btn-primary" href="{{ $continueUrl }}">{{ $en ? 'Continue learning' : 'Öğrenmeye devam et' }} <x-lucide name="arrow-right" size="14" /></a>
            @else
                <a class="btn btn-primary" href="{{ route('learning.index') }}">{{ $en ? 'Go to My Learning' : 'Eğitimlerime git' }} <x-lucide name="arrow-right" size="14" /></a>
            @endif
            @else
                @auth
                @if($unmetPrerequisites->isNotEmpty())
                <div class="due-warning is-overdue" style="text-align:left">
                    <x-lucide name="triangle-alert" size="14" />
                    {{ $en ? 'You must first complete:' : 'Önce şunu tamamlamanız gerekiyor:' }}
                    <ul style="margin:6px 0 0;padding-left:18px">
                        @foreach($unmetPrerequisites as $prerequisite)
                        <li><a href="{{ route('courses.show', $prerequisite) }}">{{ $prerequisite->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
                @elseif($isWaitlisted)
                <span class="badge">{{ $en ? "You're on the waitlist" : 'Bekleme listesindesiniz' }}</span>
                @else
                <form method="post" action="{{ route('courses.enroll', $course) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-primary">{{ $en ? 'Enroll now' : 'Eğitime kayıt ol' }} <x-lucide name="arrow-right" size="14" /></button></form>
                @endif
                @else
                <a class="btn btn-primary" href="{{ route('login') }}">{{ $en ? 'Sign in to enroll' : 'Kayıt olmak için giriş yapın' }} <x-lucide name="arrow-right" size="14" /></a>
                @endauth
            @endif
        </div>
    </div>

    <div class="card surface course-show-section">
        <h2>{{ $en ? 'About' : 'Hakkında' }}</h2>
        <p class="muted">{{ $course->description ?: ($en ? 'No description provided yet.' : 'Henüz açıklama eklenmemiş.') }}</p>
        @if($course->skills->isNotEmpty())
        <p class="muted" style="margin-bottom:6px">{{ $en ? "What you'll learn" : 'Kazanacağınız yetkinlikler' }}</p>
        <div class="skill-tags">@foreach($course->skills as $skill)<span class="skill-tag"><x-lucide name="award" size="12" /> {{ $skill->name }}</span>@endforeach</div>
        @endif
    </div>

    @if($enrollment && $stats['total'] > 0)
    <div class="card surface course-show-section">
        <h2>{{ $en ? 'Completion requirements' : 'Tamamlanma koşulları' }}</h2>
        <p class="muted">{{ $en ? 'You need to complete all required items below to earn your certificate.' : 'Sertifika kazanmak için aşağıdaki tüm zorunlu içerikleri tamamlamanız gerekir.' }}</p>
        <div class="requirement-row"><span>{{ $en ? 'Materials & sessions' : 'Materyal ve seanslar' }}</span><span class="badge">{{ $stats['completed'] }} / {{ $stats['total'] }}</span></div>
    </div>
    @endif

    @if($course->sections->isNotEmpty())
    <div class="card surface course-show-section">
        <h2>{{ $en ? 'Curriculum' : 'Müfredat' }}</h2>
        @foreach($course->sections as $section)
        <div class="curriculum-section">
            <strong>{{ $section->title }}</strong>
            <ul class="curriculum-list list-group list-group-flush">
            @foreach($section->activities as $activity)
            <li class="list-group-item">
                <span>{{ $activity->title }} @unless($activity->is_required)<small class="muted">({{ $en ? 'optional' : 'opsiyonel' }})</small>@endunless</span>
                @if($enrollment && $activity->type !== 'live_session')
                    @php($activityDone = $enrollment->activityProgress->firstWhere('activity_id', $activity->id)?->completed)
                    @if($activityDone)
                    <span class="badge"><x-lucide name="check" size="14" /></span>
                    @else
                    <a class="text-link" href="{{ route('activities.view', $activity) }}">{{ $en ? 'View content →' : 'İçeriği görüntüle →' }}</a>
                    @endif
                @elseif($enrollment && $activity->type === 'live_session')
                    <span class="badge">{{ $en ? 'Tracked via live session attendance' : 'Canlı oturum katılımıyla izlenir' }}</span>
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
        @php($sessionProgress = $enrollment?->sessionProgress->firstWhere('course_session_id', $session->id))
        <div class="curriculum-section">
            <div class="course-card-top">
                <strong>{{ $session->title }}</strong>
                <span class="muted">{{ $session->starts_at?->format('d.m.Y H:i') ?? ($en ? 'Date pending' : 'Tarih bekleniyor') }}</span>
                @if($enrollment)
                    @if($sessionProgress?->completed_at)
                    <span class="badge"><x-lucide name="check" size="14" /> {{ $en ? 'Attended' : 'Katıldı' }}</span>
                    @elseif($session->meeting_status === 'not_applicable')
                    <span class="badge">{{ $en ? 'In-person' : 'Yüz yüze' }}</span>
                    @else
                    <a class="btn btn-primary" href="{{ route('sessions.classroom', $session) }}">{{ $en ? 'Join class' : 'Derse katıl' }} <x-lucide name="arrow-right" size="14" /></a>
                    @endif
                @endif
            </div>
            @if($session->materials->isNotEmpty())
            <ul class="curriculum-list list-group list-group-flush">
            @foreach($session->materials as $material)
            <li class="list-group-item">
                <span><x-lucide name="file-text" size="14" /> {{ $material->title }}</span>
                @if($enrollment)
                    @php($materialDone = $enrollment->progress->firstWhere('material_id', $material->id)?->completed)
                    @if($materialDone)
                    <span class="badge"><x-lucide name="check" size="14" /></span>
                    @else
                    <a class="text-link" href="{{ route('materials.view', $material) }}">{{ $en ? 'View content →' : 'İçeriği görüntüle →' }}</a>
                    @endif
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

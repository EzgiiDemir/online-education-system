@extends('lms.instructor-layout')
@section('page-title', session('locale', 'tr') === 'en' ? 'Overview' : 'Genel bakış')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'INSTRUCTOR WORKSPACE' : 'EĞİTMEN ÇALIŞMA ALANI' }}</span><h1>{{ $en ? 'Overview' : 'Genel bakış' }}</h1></div></div>
<div class="row row-cols-1 row-cols-md-4 g-4 mb-5">
<div class="card metric-card"><span class="tag">{{ $en ? 'ACTIVE COURSES' : 'AKTİF EĞİTİMLER' }}</span><strong>{{ $courses->where('status','published')->count() }}</strong><small>{{ $en ? 'Your published courses' : 'Yayınlanan eğitimleriniz' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'STUDENTS' : 'ÖĞRENCİ' }}</span><strong>{{ $courses->sum('enrollments_count') }}</strong><small>{{ $en ? 'Across all your courses' : 'Tüm eğitimlerinizde' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'THIS WEEK' : 'BU HAFTA' }}</span><strong>{{ $sessionsThisWeekCount }}</strong><small>{{ $en ? 'Live sessions scheduled' : 'Planlanan canlı seans' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'PENDING TASKS' : 'BEKLEYEN İŞ' }}</span><strong style="{{ $pendingTasks->sum('count') > 0 ? 'color:#b3402e' : '' }}">{{ $pendingTasks->sum('count') }}</strong><small>{{ $en ? 'Need your attention' : 'Dikkatinizi bekliyor' }}</small></div>
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'TODAY' : 'BUGÜN' }}</span><h2>{{ $en ? "Today's schedule" : 'Bugünün programı' }}</h2></div></div>
<div class="card mb-5">
@if($todaySessions->isNotEmpty())
<ul class="list-group list-group-flush">
@foreach($todaySessions as $session)
<li class="list-group-item requirement-row">
<span><b>{{ $session->starts_at->format('H:i') }}</b> — {{ $session->title }} <span class="muted">({{ $session->course->title }})</span></span>
@if($session->meeting_url)<a class="btn btn-outline-secondary" href="{{ $session->meeting_url }}">{{ $en ? 'Join' : 'Katıl' }}</a>@endif
</li>
@endforeach
</ul>
@elseif($upcomingSession)
<div class="today-card surface" style="margin:0"><span class="badge">{{ $en ? 'NEXT SESSION' : 'SIRADAKİ SEANS' }}</span><h3 style="margin:8px 0 2px">{{ $upcomingSession->title }}</h3><p class="muted" style="margin:0">{{ $upcomingSession->course->title }} · {{ $upcomingSession->starts_at?->format('d.m.Y H:i') }}</p></div>
@else
<p class="muted" style="margin:0;padding:4px 0">{{ $en ? 'No live sessions scheduled for today.' : 'Bugün için planlanan canlı seans yok.' }}</p>
@endif
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'ATTENTION REQUIRED' : 'DİKKAT GEREKİYOR' }}</span><h2>{{ $en ? 'My tasks' : 'Yapılacaklarım' }}</h2></div></div>
<div class="card mb-5">
@if($pendingTasks->isEmpty() && $sessionsNeedingAttendanceReview->isEmpty())
<p class="muted" style="margin:0;padding:4px 0">{{ $en ? 'Nothing needs your attention right now.' : 'Şu an dikkat gerektiren bir şey yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($pendingTasks as $task)
<li class="list-group-item requirement-row">
<span><span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $task['count'] }}</span> {{ $en ? $task['label_en'] : $task['label_tr'] }}</span>
<a class="text-link" href="{{ $task['href'] }}">{{ $en ? 'Review →' : 'İncele →' }}</a>
</li>
@endforeach
</ul>
@if($sessionsNeedingAttendanceReview->isNotEmpty())
<div style="margin-top:{{ $pendingTasks->isNotEmpty() ? '16px' : '0' }}">
<span class="badge">{{ $en ? 'PARTICIPANT LIST REVIEW' : 'KATILIMCI LİSTESİ KONTROLÜ' }}</span>
<ul class="curriculum-list" style="margin-top:8px">
@foreach($sessionsNeedingAttendanceReview as $needsReview)
<li><span>{{ $needsReview->title }} — {{ $needsReview->course->title }}</span><a class="text-link" href="{{ route('instructor.courses.show', $needsReview->course) }}#sessions">{{ $en ? 'Review →' : 'İncele →' }}</a></li>
@endforeach
</ul>
</div>
@endif
@endif
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'RECENT ACTIVITY' : 'SON HAREKETLER' }}</span><h2>{{ $en ? 'What happened recently' : 'Son olanlar' }}</h2></div></div>
<div class="card mb-5">
@if($recentActivity->isEmpty())
<p class="muted" style="margin:0;padding:4px 0">{{ $en ? 'No activity in the last 7 days.' : 'Son 7 günde bir hareket yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($recentActivity as $activity)
<li class="list-group-item requirement-row">
<span>{{ $en ? $activity['text_en'] : $activity['text_tr'] }}</span>
<small class="muted">{{ $activity['at']->diffForHumans() }}</small>
</li>
@endforeach
</ul>
@endif
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'CONTENT MANAGEMENT' : 'İÇERİK YÖNETİMİ' }}</span><h2>{{ $en ? 'My Courses' : 'Eğitimlerim' }}</h2></div><a class="btn btn-primary" href="{{ route('instructor.courses.index') }}">{{ $en ? 'Manage courses' : 'Eğitimleri yönet' }} <x-lucide name="arrow-right" size="14" /></a></div>
@endsection

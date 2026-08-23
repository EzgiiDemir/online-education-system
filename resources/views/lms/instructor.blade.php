@extends('lms.instructor-layout')
@section('page-title', session('locale', 'tr') === 'en' ? 'Overview' : 'Genel bakış')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="row row-cols-1 row-cols-md-3 g-4 mb-5"><div class="card metric-card"><span class="tag">{{ $en ? 'ACTIVE COURSES' : 'AKTİF EĞİTİMLER' }}</span><strong>{{ $courses->where('status','published')->count() }}</strong><small>{{ $en ? 'Your published courses' : 'Yayınlanan eğitimleriniz' }}</small></div><div class="card metric-card"><span class="tag">{{ $en ? 'TOTAL PARTICIPANTS' : 'TOPLAM KATILIMCI' }}</span><strong>{{ $courses->sum('enrollments_count') }}</strong><small>{{ $en ? 'Across all your courses' : 'Tüm eğitimlerinizde' }}</small></div><div class="card metric-card"><span class="tag">{{ $en ? 'SCHEDULED SESSIONS' : 'PLANLANAN SEANS' }}</span><strong>{{ $courses->sum(fn($course)=>$course->sessions->count()) }}</strong><small>{{ $en ? 'Online and offline sessions' : 'Online ve offline seans' }}</small></div></div>
<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'TODAY' : 'BUGÜN' }}</span><h2>{{ $en ? "Today's actions" : 'Bugünün eylemleri' }}</h2></div></div>
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 mb-5">
@if($upcomingSession)
<div class="today-card surface"><span class="status">{{ $en ? 'NEXT SESSION' : 'SIRADAKİ SEANS' }}</span><h3>{{ $upcomingSession->title }}</h3><p class="muted">{{ $upcomingSession->course->title }} · {{ $upcomingSession->starts_at?->format('d.m.Y H:i') }}</p></div>
@endif
@if($sessionsNeedingAttendanceReview->isNotEmpty())
<div class="today-card surface"><span class="status">{{ $en ? 'ATTENDANCE REVIEW' : 'YOKLAMA KONTROLÜ' }}</span><h3>{{ $sessionsNeedingAttendanceReview->count() }} {{ $en ? 'sessions need review' : 'seans incelenmeli' }}</h3><p class="muted">{{ $en ? "There are sessions in the last 7 days with no attendance marked." : 'Son 7 günde katılımı işaretlenmemiş seanslar var.' }}</p><ul class="curriculum-list">@foreach($sessionsNeedingAttendanceReview->take(3) as $needsReview)<li><span>{{ $needsReview->title }} — {{ $needsReview->course->title }}</span><a class="text-link" href="{{ route('instructor.courses.show', $needsReview->course) }}">{{ $en ? 'Review →' : 'İncele →' }}</a></li>@endforeach</ul></div>
@endif
@if($incompleteLearnersCount > 0)
<div class="today-card surface"><span class="status">{{ $en ? 'LEARNERS IN PROGRESS' : 'DEVAM EDEN ÖĞRENCİ' }}</span><h3>{{ $incompleteLearnersCount }}</h3><p class="muted">{{ $en ? 'Participants who have not completed yet.' : 'Henüz tamamlamamış katılımcı sayısı.' }}</p></div>
@endif
@if($newEnrollmentsCount > 0)
<div class="today-card surface"><span class="status">{{ $en ? 'NEW ENROLLMENTS' : 'YENİ KAYIT' }}</span><h3>{{ $newEnrollmentsCount }}</h3><p class="muted">{{ $en ? 'New enrollments in the last 7 days.' : 'Son 7 günde yapılan yeni kayıtlar.' }}</p></div>
@endif
@if(!$upcomingSession && $sessionsNeedingAttendanceReview->isEmpty() && $incompleteLearnersCount === 0 && $newEnrollmentsCount === 0)
<div class="today-card surface"><p class="muted" style="margin:0">{{ $en ? 'Nothing needs your attention right now.' : 'Şu an dikkat gerektiren bir şey yok.' }}</p></div>
@endif
</div>
<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'CONTENT MANAGEMENT' : 'İÇERİK YÖNETİMİ' }}</span><h2>{{ $en ? 'My Courses' : 'Eğitimlerim' }}</h2></div><a class="button" href="{{ route('instructor.courses.index') }}">{{ $en ? 'Manage courses' : 'Eğitimleri yönet' }} <x-lucide name="arrow-right" size="14" /></a></div>
@endsection

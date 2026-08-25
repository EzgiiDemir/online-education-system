@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'My Learning' : 'Öğrenim alanım').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'MY LEARNING' : 'ÖĞRENİM ALANIM' }}</span><h1>{{ $en ? 'My Courses' : 'Eğitimlerim' }}</h1><p class="muted">{{ $en ? 'Courses you are enrolled in and upcoming live classes.' : 'Kayıt olduğunuz eğitimler ve yaklaşan canlı dersler.' }}</p></div></div>

<form method="get" class="report-filters" style="margin-bottom:14px">
<input type="hidden" name="tab" value="{{ $tab }}">
<div class="field" style="flex:2;max-width:420px"><label for="ml-q">{{ $en ? 'Search your courses' : 'Eğitimlerinizde arayın' }}</label><input id="ml-q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $en ? 'Search your courses...' : 'Eğitimlerinizde arayın...' }}"></div>
</form>

<ul class="nav nav-tabs">
<li class="nav-item"><a href="{{ route('learning.index', ['tab' => 'all']) }}" class="nav-link {{ $tab === 'all' ? 'active' : '' }}">{{ $en ? 'All' : 'Tümü' }} <span class="tab-count">{{ $counts['all'] }}</span></a></li>
<li class="nav-item"><a href="{{ route('learning.index', ['tab' => 'in_progress']) }}" class="nav-link {{ $tab === 'in_progress' ? 'active' : '' }}">{{ $en ? 'In progress' : 'Devam ediyor' }} <span class="tab-count">{{ $counts['in_progress'] }}</span></a></li>
<li class="nav-item"><a href="{{ route('learning.index', ['tab' => 'upcoming']) }}" class="nav-link {{ $tab === 'upcoming' ? 'active' : '' }}">{{ $en ? 'Upcoming' : 'Yaklaşan' }} <span class="tab-count">{{ $counts['upcoming'] }}</span></a></li>
<li class="nav-item"><a href="{{ route('learning.index', ['tab' => 'completed']) }}" class="nav-link {{ $tab === 'completed' ? 'active' : '' }}">{{ $en ? 'Completed' : 'Tamamlanan' }} <span class="tab-count">{{ $counts['completed'] }}</span></a></li>
<li class="nav-item"><a href="{{ route('learning.index', ['tab' => 'certificates']) }}" class="nav-link {{ $tab === 'certificates' ? 'active' : '' }}">{{ $en ? 'Certificates' : 'Sertifikalar' }} <span class="tab-count">{{ $counts['certificates'] }}</span></a></li>
</ul>

<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4">
@forelse($enrollments as $enrollment)
@php($course = $enrollment->course)
@php($percent = $enrollment->completionStats()['percent'])
@php($nextSession = $course->sessions->where('starts_at', '>', now())->sortBy('starts_at')->first())
<article class="course-card surface">
<div class="course-card-top">
<span class="badge">{{ $course->delivery_type === 'online' ? ($en ? 'LIVE CLASS' : 'CANLI SINIF') : ($en ? 'SELF-PACED' : 'İÇERİK TABANLI') }}</span>
<span class="badge">{{ ['enrolled' => $en ? 'Enrolled' : 'Kayıtlı', 'in_progress' => $en ? 'In progress' : 'Devam ediyor', 'completed' => $en ? 'Completed' : 'Tamamlandı'][$enrollment->status] ?? $enrollment->status }}</span>
</div>
<h2><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h2>
<p class="muted">{{ \Illuminate\Support\Str::limit($course->description, 100) }}</p>
<div class="meter"><span style="width:{{ $percent }}%"></span></div>
<small class="muted">%{{ $percent }} {{ $en ? 'completed' : 'tamamlandı' }}</small>
@if($nextSession)
<p class="muted" style="margin:8px 0 0;font-size:12.5px"><x-lucide name="calendar" size="13" /> {{ $nextSession->title }} · {{ $nextSession->starts_at->format('d.m.Y H:i') }}</p>
@endif
<div class="course-card-actions" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
@if($enrollment->certificate?->isRevoked())
<span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Certificate revoked' : 'Sertifika iptal edildi' }}</span>
@elseif($enrollment->certificate)
<a class="btn btn-primary" href="{{ route('certificates.download', $enrollment->certificate) }}">{{ $en ? 'Download certificate' : 'Sertifikayı indir' }}</a>
@else
<a class="btn btn-primary" href="{{ route('courses.show', $course) }}">{{ $en ? 'Continue' : 'Devam et' }} <x-lucide name="arrow-right" size="14" /></a>
@endif
</div>
</article>
@empty
<div class="empty-state surface">@if($tab !== 'all' || request('q')){{ $en ? 'No courses to show here.' : 'Burada gösterilecek bir eğitim yok.' }}@else {{ $en ? "You aren't enrolled in any training yet." : 'Henüz kayıt olduğunuz bir eğitim yok.' }} <a href="{{ route('lms.home') }}">{{ $en ? 'Browse the catalog →' : 'Kataloğu görüntüleyin →' }}</a>@endif</div>
@endforelse
</div>
{{ $enrollments->links('lms.partials.pagination') }}
@endsection

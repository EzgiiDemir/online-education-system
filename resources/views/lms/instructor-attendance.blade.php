@extends('lms.instructor-layout')
@section('page-title', session('locale', 'tr') === 'en' ? 'Participant List' : 'Katılımcı Listesi')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'PARTICIPANT LIST' : 'KATILIMCI LİSTESİ' }}</span><h1>{{ $en ? 'Participant list' : 'Katılımcı listesi' }}</h1></div></div>

@if(!$selectedCourse)
{{-- Step 1: pick a course --}}
<form method="get" class="report-filters">
<div class="field" style="flex:2"><label for="att-q">{{ $en ? 'Search your courses' : 'Eğitimlerinizde arayın' }}</label><input id="att-q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $en ? 'Search your courses...' : 'Eğitimlerinizde arayın...' }}"></div>
<div class="field" style="flex:0;align-self:flex-end;display:flex;gap:8px;white-space:nowrap">
<button type="submit" class="btn btn-primary">{{ $en ? 'Search' : 'Ara' }}</button>
@if(request('q'))<a class="btn btn-outline-secondary" href="{{ route('instructor.attendance') }}">{{ $en ? 'Clear' : 'Temizle' }}</a>@endif
</div>
</form>
<div class="card">
<h2 style="margin-top:0">{{ $en ? 'Select a course' : 'Bir eğitim seçin' }}</h2>
@if($courses->isEmpty())
<p class="muted">{{ $en ? 'No courses match your search.' : 'Aramanızla eşleşen eğitim yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($courses as $course)
<li class="list-group-item requirement-row">
<span><b>{{ $course->title }}</b> <small class="muted">{{ $course->sessions_count }} {{ $en ? 'sessions' : 'seans' }}</small></span>
<a class="btn btn-outline-secondary" href="{{ route('instructor.attendance', ['course_id' => $course->id]) }}">{{ $en ? 'Select →' : 'Seç →' }}</a>
</li>
@endforeach
</ul>
@endif
</div>
@elseif(!$selectedSession)
{{-- Step 2: pick a session --}}
<div class="section-heading instructor-section-heading" style="margin-top:0"><div><span class="eyebrow">{{ $selectedCourse->title }}</span><h2>{{ $en ? 'Select a session' : 'Bir seans seçin' }}</h2></div><a class="btn btn-outline-secondary" href="{{ route('instructor.attendance') }}">{{ $en ? '← Change course' : '← Eğitimi değiştir' }}</a></div>
<div class="card">
@if($sessions->isEmpty())
<p class="muted">{{ $en ? 'This course has no sessions yet.' : 'Bu eğitimde henüz seans yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($sessions as $session)
<li class="list-group-item requirement-row">
<span><b>{{ $session->title }}</b> <small class="muted">{{ $session->starts_at?->format('d.m.Y H:i') ?? ($en ? 'Date pending' : 'Tarih bekleniyor') }}</small></span>
<a class="btn btn-outline-secondary" href="{{ route('instructor.attendance', ['course_id' => $selectedCourse->id, 'session_id' => $session->id]) }}">{{ $en ? 'Select →' : 'Seç →' }}</a>
</li>
@endforeach
</ul>
@endif
</div>
@else
{{-- Step 3: roster --}}
<div class="section-heading instructor-section-heading" style="margin-top:0"><div><span class="eyebrow">{{ $selectedCourse->title }}</span><h2>{{ $selectedSession->title }}</h2></div><span style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-outline-secondary" href="{{ route('instructor.sessions.attendance.export', $selectedSession) }}">{{ $en ? 'Export CSV' : 'CSV olarak dışa aktar' }}</a><a class="btn btn-outline-secondary" href="{{ route('instructor.attendance', ['course_id' => $selectedCourse->id]) }}">{{ $en ? '← Change session' : '← Seansı değiştir' }}</a></span></div>
<div class="card">
@if($selectedSession->meeting_status === 'not_applicable')
<p class="muted" style="margin-top:0">{{ $en ? 'In-person session — attendance only counts if you mark it here.' : 'Yüz yüze seans — katılım yalnızca burada işaretlerseniz sayılır.' }}</p>
@else
<p class="muted" style="margin-top:0">{{ $en ? 'Attendance for an online session is counted automatically once a participant stays in the room long enough; you can also mark it here if there is a connection issue.' : 'Online seansta katılım, katılımcı odada yeterli süre kalınca otomatik sayılır; bağlantı sorunu olursa buradan da işaretleyebilirsiniz.' }}</p>
@endif
@if($roster->isEmpty())
<p class="muted">{{ $en ? 'No participants in this course.' : 'Bu eğitimde katılımcı yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($roster as $row)
<li class="list-group-item requirement-row">
<span>{{ $row['enrollment']->user->name }} <small class="muted">{{ $row['enrollment']->user->email }}</small></span>
@if($row['attended']?->completed_at)
<span class="muted"><x-lucide name="check" size="14" /> {{ $en ? 'Attended' : 'Katıldı' }} ({{ $row['attended']->completed_at->format('d.m.Y H:i') }})</span>
@else
<form method="post" action="{{ route('instructor.sessions.attendance', $selectedSession) }}" class="inline-form">
@csrf
<input type="hidden" name="user_id" value="{{ $row['enrollment']->user_id }}">
<button class="btn btn-outline-secondary" type="submit">{{ $en ? 'Mark as attended' : 'Katıldı olarak işaretle' }}</button>
</form>
@endif
</li>
@endforeach
</ul>
@endif
</div>
@endif
@endsection

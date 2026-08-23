@extends('lms.layout')
@section('title', ($query !== '' ? '"'.$query.'" · ' : '').(session('locale', 'tr') === 'en' ? 'Search' : 'Arama').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
@php($totalCount = $results['courses']->count() + $results['activities']->count() + $results['materials']->count() + $results['sessions']->count() + $results['instructors']->count())
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'SEARCH' : 'ARAMA' }}</span><h1>@if($query !== ''){{ $en ? 'Results for' : 'Sonuçlar:' }} "{{ $query }}"@else{{ $en ? 'Search' : 'Ara' }}@endif</h1></div></div>

<form method="get" action="{{ route('search.index') }}" class="search-form">
<input type="search" name="q" value="{{ $query }}" placeholder="{{ $en ? 'Search courses, activities, materials, sessions, instructors…' : 'Eğitim, etkinlik, materyal, seans, eğitmen ara…' }}" autofocus>
<button type="submit" class="btn btn-primary">{{ $en ? 'Search' : 'Ara' }}</button>
</form>

@if($query !== '')
<div class="tab-bar">
<a href="{{ route('search.index', ['q' => $query, 'type' => 'all']) }}" class="{{ $type === 'all' ? 'is-active' : '' }}">{{ $en ? 'All' : 'Tümü' }} <span class="tab-count">{{ $totalCount }}</span></a>
<a href="{{ route('search.index', ['q' => $query, 'type' => 'courses']) }}" class="{{ $type === 'courses' ? 'is-active' : '' }}">{{ $en ? 'Courses' : 'Eğitimler' }} <span class="tab-count">{{ $results['courses']->count() }}</span></a>
<a href="{{ route('search.index', ['q' => $query, 'type' => 'activities']) }}" class="{{ $type === 'activities' ? 'is-active' : '' }}">{{ $en ? 'Activities' : 'Etkinlikler' }} <span class="tab-count">{{ $results['activities']->count() }}</span></a>
<a href="{{ route('search.index', ['q' => $query, 'type' => 'materials']) }}" class="{{ $type === 'materials' ? 'is-active' : '' }}">{{ $en ? 'Materials' : 'Materyaller' }} <span class="tab-count">{{ $results['materials']->count() }}</span></a>
<a href="{{ route('search.index', ['q' => $query, 'type' => 'sessions']) }}" class="{{ $type === 'sessions' ? 'is-active' : '' }}">{{ $en ? 'Sessions' : 'Seanslar' }} <span class="tab-count">{{ $results['sessions']->count() }}</span></a>
<a href="{{ route('search.index', ['q' => $query, 'type' => 'instructors']) }}" class="{{ $type === 'instructors' ? 'is-active' : '' }}">{{ $en ? 'Instructors' : 'Eğitmenler' }} <span class="tab-count">{{ $results['instructors']->count() }}</span></a>
</div>

@if($totalCount === 0)
<div class="empty-state surface">{{ $en ? 'No results found. Try a different search term.' : 'Sonuç bulunamadı. Farklı bir arama terimi deneyin.' }}</div>
@else
@if($results['courses']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Courses' : 'Eğitimler' }}</h2>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">@foreach($results['courses'] as $course)<article class="course-card surface" style="--course-accent:var(--course-accent-{{ $course->id % 6 }});border-left:4px solid var(--course-accent)"><h3><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h3><p class="muted">{{ \Illuminate\Support\Str::limit($course->description, 100) }}</p><span class="muted"><x-lucide name="user" size="14" /> {{ $course->instructor->name }}</span></article>@endforeach</div>
@endif

@if($results['activities']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Activities' : 'Etkinlikler' }}</h2>
<div class="card surface">@foreach($results['activities'] as $activity)<div class="requirement-row"><span><a href="{{ route('activities.view', $activity) }}">{{ $activity->title }}</a> <small class="muted">— {{ $activity->section->course->title }}</small></span></div>@endforeach</div>
@endif

@if($results['materials']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Materials' : 'Materyaller' }}</h2>
<div class="card surface">@foreach($results['materials'] as $material)<div class="requirement-row"><span><x-lucide name="file-text" size="14" /> <a href="{{ route('materials.view', $material) }}">{{ $material->title }}</a> <small class="muted">— {{ $material->session->course->title }}</small></span></div>@endforeach</div>
@endif

@if($results['sessions']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Sessions' : 'Seanslar' }}</h2>
<div class="card surface">@foreach($results['sessions'] as $session)<div class="requirement-row"><span>{{ $session->title }} <small class="muted">— {{ $session->course->title }}</small></span><a class="btn btn-outline-secondary" href="{{ route('courses.show', $session->course) }}">{{ $en ? 'View course' : 'Eğitimi görüntüle' }}</a></div>@endforeach</div>
@endif

@if($results['instructors']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Instructors' : 'Eğitmenler' }}</h2>
<div class="card surface">@foreach($results['instructors'] as $instructor)<div class="requirement-row"><span><x-lucide name="user" size="14" /> {{ $instructor->name }}</span></div>@endforeach</div>
@endif
@endif
@else
<div class="empty-state surface">{{ $en ? 'Type something above to search the whole portal.' : 'Portalın tamamında aramak için yukarıya bir şeyler yazın.' }}</div>
@endif
@endsection

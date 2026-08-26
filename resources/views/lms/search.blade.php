@extends('lms.layout')
@section('title', ($query !== '' ? '"'.$query.'" · ' : '').(session('locale', 'tr') === 'en' ? 'Search' : 'Arama').' · '.(session('locale', 'tr') === 'en' ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
@php($totalCount = $results['courses']->count() + $results['activities']->count() + $results['materials']->count() + $results['sessions']->count() + $results['instructors']->count())
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'SEARCH' : 'ARAMA' }}</span><h1>@if($query !== ''){{ $en ? 'Results for' : 'Sonuçlar:' }} "{{ $query }}"@else{{ $en ? 'Search' : 'Ara' }}@endif</h1></div></div>

<form method="get" action="{{ route('search.index') }}" class="search-form">
<input type="search" name="q" value="{{ $query }}" placeholder="{{ $en ? 'Search courses, activities, materials, sessions, instructors…' : 'Eğitim, etkinlik, materyal, seans, eğitmen ara…' }}" autofocus>
<button type="submit" class="btn btn-primary">{{ $en ? 'Search' : 'Ara' }}</button>
</form>

@if($query !== '')
<ul class="nav nav-tabs">
<li class="nav-item"><a href="{{ route('search.index', ['q' => $query, 'type' => 'all']) }}" class="nav-link {{ $type === 'all' ? 'active' : '' }}">{{ $en ? 'All' : 'Tümü' }} <span class="tab-count">{{ $totalCount }}</span></a></li>
<li class="nav-item"><a href="{{ route('search.index', ['q' => $query, 'type' => 'courses']) }}" class="nav-link {{ $type === 'courses' ? 'active' : '' }}">{{ $en ? 'Courses' : 'Eğitimler' }} <span class="tab-count">{{ $results['courses']->count() }}</span></a></li>
<li class="nav-item"><a href="{{ route('search.index', ['q' => $query, 'type' => 'activities']) }}" class="nav-link {{ $type === 'activities' ? 'active' : '' }}">{{ $en ? 'Activities' : 'Etkinlikler' }} <span class="tab-count">{{ $results['activities']->count() }}</span></a></li>
<li class="nav-item"><a href="{{ route('search.index', ['q' => $query, 'type' => 'materials']) }}" class="nav-link {{ $type === 'materials' ? 'active' : '' }}">{{ $en ? 'Materials' : 'Materyaller' }} <span class="tab-count">{{ $results['materials']->count() }}</span></a></li>
<li class="nav-item"><a href="{{ route('search.index', ['q' => $query, 'type' => 'sessions']) }}" class="nav-link {{ $type === 'sessions' ? 'active' : '' }}">{{ $en ? 'Sessions' : 'Seanslar' }} <span class="tab-count">{{ $results['sessions']->count() }}</span></a></li>
<li class="nav-item"><a href="{{ route('search.index', ['q' => $query, 'type' => 'instructors']) }}" class="nav-link {{ $type === 'instructors' ? 'active' : '' }}">{{ $en ? 'Instructors' : 'Eğitmenler' }} <span class="tab-count">{{ $results['instructors']->count() }}</span></a></li>
</ul>

@if($totalCount === 0)
<div class="empty-state surface">{{ $en ? 'No results found. Try a different search term.' : 'Sonuç bulunamadı. Farklı bir arama terimi deneyin.' }}</div>
@else
@if($results['courses']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Courses' : 'Eğitimler' }}</h2>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">@foreach($results['courses'] as $course)<article class="course-card surface" style="--course-accent:var(--course-accent-{{ $course->id % 6 }});border-left:4px solid var(--course-accent)"><h3><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></h3><p class="muted">{{ \Illuminate\Support\Str::limit($course->description, 100) }}</p><span class="muted"><x-lucide name="user" size="14" /> {{ $course->instructor->name }}</span></article>@endforeach</div>
@endif

@if($results['activities']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Activities' : 'Etkinlikler' }}</h2>
<div class="card surface"><ul class="list-group list-group-flush">@foreach($results['activities'] as $activity)<li class="list-group-item requirement-row"><span><a href="{{ route('activities.view', $activity) }}">{{ $activity->title }}</a> <small class="muted">— {{ $activity->section->course->title }}</small></span></li>@endforeach</ul></div>
@endif

@if($results['materials']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Materials' : 'Materyaller' }}</h2>
<div class="card surface"><ul class="list-group list-group-flush">@foreach($results['materials'] as $material)<li class="list-group-item requirement-row"><span><x-lucide name="file-text" size="14" /> <a href="{{ route('materials.view', $material) }}">{{ $material->title }}</a> <small class="muted">— {{ $material->session->course->title }}</small></span></li>@endforeach</ul></div>
@endif

@if($results['sessions']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Sessions' : 'Seanslar' }}</h2>
<div class="card surface"><ul class="list-group list-group-flush">@foreach($results['sessions'] as $session)<li class="list-group-item requirement-row"><span>{{ $session->title }} <small class="muted">— {{ $session->course->title }}</small></span><a class="btn btn-outline-secondary" href="{{ route('courses.show', $session->course) }}">{{ $en ? 'View course' : 'Eğitimi görüntüle' }}</a></li>@endforeach</ul></div>
@endif

@if($results['instructors']->isNotEmpty())
<h2 class="search-group-title">{{ $en ? 'Instructors' : 'Eğitmenler' }}</h2>
<div class="card surface"><ul class="list-group list-group-flush">@foreach($results['instructors'] as $instructor)<li class="list-group-item requirement-row"><span><x-lucide name="user" size="14" /> {{ $instructor->name }}</span></li>@endforeach</ul></div>
@endif
@endif
@else
<div class="empty-state surface">{{ $en ? 'Type something above to search the whole portal.' : 'Portalın tamamında aramak için yukarıya bir şeyler yazın.' }}</div>
@endif
@endsection

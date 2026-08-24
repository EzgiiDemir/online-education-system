@extends('lms.instructor-layout')
@section('page-title', session('locale', 'tr') === 'en' ? 'Content Library' : 'İçerik Kütüphanesi')
@section('content')
@php($en = session('locale', 'tr') === 'en')
@php($typeLabels = ['video' => $en ? 'Videos' : 'Videolar', 'document' => $en ? 'Documents' : 'Dokümanlar', 'presentation' => $en ? 'Presentations' : 'Sunumlar', 'quiz' => $en ? 'Quizzes' : 'Quizler', 'assignment' => $en ? 'Assignments' : 'Ödevler', 'live_session' => $en ? 'Live Sessions' : 'Canlı Seanslar'])
@php($total = $typeCounts->sum())

<div class="section-heading instructor-section-heading" style="margin-top:0"><div><span class="eyebrow">{{ $en ? 'CONTENT LIBRARY' : 'İÇERİK KÜTÜPHANESİ' }}</span><h2>{{ $en ? 'All content across your courses' : 'Tüm eğitimlerinizdeki içerikler' }}</h2></div></div>

<form method="get" class="card" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:end">
@if($activeType)<input type="hidden" name="type" value="{{ $activeType }}">@endif
<label style="flex:1;min-width:200px">{{ $en ? 'Search content...' : 'İçerikte ara...' }}<input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $en ? 'Search content...' : 'İçerikte ara...' }}"></label>
<label>{{ $en ? 'Course' : 'Eğitim' }}<select name="course_id"><option value="">{{ $en ? 'All courses' : 'Tüm eğitimler' }}</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->title }}</option>@endforeach</select></label>
<label>{{ $en ? 'Language' : 'Dil' }}<select name="language"><option value="">{{ $en ? 'All' : 'Tümü' }}</option><option value="tr" @selected(request('language')==='tr')>Türkçe</option><option value="en" @selected(request('language')==='en')>English</option></select></label>
<button type="submit" class="btn btn-primary">{{ $en ? 'Filter' : 'Filtrele' }}</button>
@if(request('q') || request('course_id') || request('language') || $activeType)<a class="btn btn-outline-secondary" href="{{ route('instructor.content-library') }}">{{ $en ? 'Clear' : 'Temizle' }}</a>@endif
</form>

@php($baseQuery = array_filter(request()->except(['type', 'page'])))
<ul class="nav nav-tabs">
<li class="nav-item"><a class="nav-link {{ !$activeType ? 'is-active' : '' }}" href="{{ route('instructor.content-library', $baseQuery) }}">{{ $en ? 'All Content' : 'Tüm İçerikler' }} <span class="tab-count">{{ $total }}</span></a></li>
@foreach($typeLabels as $key => $label)
@if($typeCounts->get($key, 0) > 0 || $activeType === $key)
<li class="nav-item"><a class="nav-link {{ $activeType === $key ? 'is-active' : '' }}" href="{{ route('instructor.content-library', $baseQuery + ['type' => $key]) }}">{{ $label }} <span class="tab-count">{{ $typeCounts->get($key, 0) }}</span></a></li>
@endif
@endforeach
</ul>

<div class="card">
@if($items->isEmpty())
<p class="muted" style="margin:0">{{ $en ? 'No content matches these filters.' : 'Bu filtrelere uyan içerik yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($items as $item)
<li class="list-group-item requirement-row">
<span><b>{{ $item['title'] }}</b> <span class="badge">{{ $typeLabels[$item['type']] ?? $item['type'] }}</span> <small class="muted">{{ $item['course'] }} · {{ $item['updated_at']?->format('d.m.Y') }}</small></span>
<span>
@if($item['preview_url'])<a class="text-link" href="{{ $item['preview_url'] }}" target="_blank" rel="noopener">{{ $en ? 'Preview' : 'Önizle' }}</a>@endif
<a class="text-link" href="{{ $item['edit_url'] }}">{{ $en ? 'Edit →' : 'Düzenle →' }}</a>
</span>
</li>
@endforeach
</ul>
{{ $items->links('lms.partials.pagination') }}
@endif
</div>
@endsection

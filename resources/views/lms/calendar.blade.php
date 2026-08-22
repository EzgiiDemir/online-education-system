@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Calendar' : 'Takvim').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
@php($prevMonth = $month->copy()->subMonth()->format('Y-m'))
@php($nextMonth = $month->copy()->addMonth()->format('Y-m'))
@php($firstOfMonth = $month->copy()->startOfMonth())
@php($daysInMonth = $month->daysInMonth)
@php($leadingBlanks = ($firstOfMonth->dayOfWeekIso - 1))
@php($monthLabels = $en ? ['January','February','March','April','May','June','July','August','September','October','November','December'] : ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'])
@php($dayLabels = $en ? ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] : ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'])

<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'CALENDAR' : 'TAKVİM' }}</span><h1>{{ $monthLabels[$month->month - 1] }} {{ $month->year }}</h1></div>
<div style="display:flex;gap:8px">
<a class="button secondary" href="{{ route('calendar.index', ['month' => $prevMonth]) }}">← {{ $en ? 'Prev' : 'Önceki' }}</a>
<a class="button secondary" href="{{ route('calendar.index', ['month' => $nextMonth]) }}">{{ $en ? 'Next' : 'Sonraki' }} →</a>
</div>
</div>

<div class="calendar-legend">
<span><i class="dot dot-session"></i> {{ $en ? 'Live session' : 'Canlı ders' }}</span>
<span><i class="dot dot-due"></i> {{ $en ? 'Due date' : 'Son tarih' }}</span>
<span><i class="dot dot-expiry"></i> {{ $en ? 'Certificate expiry' : 'Sertifika süresi' }}</span>
</div>

<div class="calendar-grid">
@foreach($dayLabels as $label)<div class="calendar-dow">{{ $label }}</div>@endforeach
@for($i = 0; $i < $leadingBlanks; $i++)<div class="calendar-cell is-blank"></div>@endfor
@for($day = 1; $day <= $daysInMonth; $day++)
@php($cellDate = $month->copy()->day($day)->toDateString())
@php($dayEvents = $eventsByDate->get($cellDate, collect()))
<div class="calendar-cell{{ $cellDate === now()->toDateString() ? ' is-today' : '' }}">
<span class="calendar-day-num">{{ $day }}</span>
@foreach($dayEvents->take(3) as $event)
<a href="{{ $event['url'] }}" class="calendar-event calendar-event-{{ $event['type'] }}">{{ \Illuminate\Support\Str::limit($event['title'], 18) }}</a>
@endforeach
@if($dayEvents->count() > 3)<small class="muted">+{{ $dayEvents->count() - 3 }} {{ $en ? 'more' : 'daha' }}</small>@endif
</div>
@endfor
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'AGENDA' : 'AJANDA' }}</span><h2>{{ $en ? 'Upcoming' : 'Yaklaşan' }}</h2></div></div>
<div class="card surface">
@forelse($upcoming->take(20) as $event)
<div class="requirement-row">
<span><i class="dot dot-{{ $event['type'] }}"></i> <a href="{{ $event['url'] }}">{{ $event['title'] }}</a> <small class="muted">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d.m.Y') }}{{ $event['time'] ? ' · '.$event['time'] : '' }}</small></span>
@if($event['ics'])<a class="button secondary" href="{{ $event['ics'] }}">{{ $en ? 'Add to calendar' : 'Takvime ekle' }}</a>@endif
</div>
@empty
<p class="muted" style="margin:0">{{ $en ? 'Nothing scheduled yet.' : 'Henüz planlanmış bir şey yok.' }}</p>
@endforelse
</div>
@endsection

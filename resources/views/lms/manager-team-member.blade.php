@extends('lms.manager-layout')
@section('page-title', $user->name)
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'TEAM MEMBER' : 'EKİP ÜYESİ' }}</span><h1>{{ $user->name }}</h1><p class="muted">{{ $user->job_title ?: $user->email }} @if($user->department) · {{ $user->department }} @endif @unless($isDirectReport) · <span class="muted">{{ $en ? 'sub-team' : 'alt ekip' }}</span> @endunless</p></div>
<a class="btn btn-outline-secondary" href="{{ route('manager.dashboard') }}#team">{{ $en ? '← Back to team' : '← Ekibe dön' }}</a>
</div>

<div class="card">
<h2 style="margin-top:0">{{ $en ? 'Training record' : 'Eğitim geçmişi' }}</h2>
@if($enrollments->isEmpty())
<p class="muted" style="margin:0">{{ $en ? 'No enrollments yet.' : 'Henüz kayıt yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($enrollments as $row)
<li class="list-group-item requirement-row">
<span>
<b>{{ $row['enrollment']->course->title }}</b>
<div class="meter" style="width:140px;margin-top:4px"><span style="width:{{ $row['percent'] }}%"></span></div>
</span>
<span style="display:flex;align-items:center;gap:8px">
@if($row['overdue'])<span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Overdue' : 'Süresi geçti' }}</span>@endif
@if($row['stale'])<span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Recertification due' : 'Yeniden sertifika gerekiyor' }}</span>@endif
@if($row['enrollment']->certificate && ! $row['enrollment']->certificate->isRevoked())<span class="badge" style="background:#eaf0e4;color:#4d6b2f">{{ $en ? 'Certified' : 'Sertifikalı' }}</span>@endif
<small class="muted">%{{ $row['percent'] }}</small>
</span>
</li>
@endforeach
</ul>
@endif
</div>
@endsection

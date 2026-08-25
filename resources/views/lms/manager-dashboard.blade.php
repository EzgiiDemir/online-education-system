@extends('lms.manager-layout')
@section('page-title','Ekip genel bakış')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'MANAGER WORKSPACE' : 'YÖNETİCİ ÇALIŞMA ALANI' }}</span><h1>{{ $en ? 'Team overview' : 'Ekip genel bakış' }}</h1></div></div>

<ul class="nav nav-tabs" id="manager-workspace-tabs">
<li class="nav-item"><a class="nav-link is-active" href="#overview" data-tab="overview">{{ $en ? 'Overview' : 'Genel bakış' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#team" data-tab="team">{{ $en ? 'Team' : 'Ekip' }} <span class="tab-count">{{ $team->count() }}</span></a></li>
<li class="nav-item"><a class="nav-link" href="#training" data-tab="training">{{ $en ? 'Training' : 'Eğitim Takibi' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#compliance" data-tab="compliance">{{ $en ? 'Compliance' : 'Uyumluluk' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#reports" data-tab="reports">{{ $en ? 'Reports' : 'Raporlar' }}</a></li>
</ul>

{{-- ============ OVERVIEW ============ --}}
<div data-tab-panel="overview">
<div class="row row-cols-1 row-cols-md-4 g-4 mb-5 mt-1">
<div class="card metric-card"><span class="tag">{{ $en ? 'TEAM COMPLIANCE' : 'EKİP UYUMLULUĞU' }}</span><strong style="{{ $complianceRate !== null && $complianceRate < 80 ? 'color:#b3402e' : '' }}">{{ $complianceRate !== null ? '%'.$complianceRate : '—' }}</strong><small class="muted">{{ $en ? 'Mandatory training' : 'Zorunlu eğitimler' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'OVERDUE' : 'SÜRESİ GEÇEN' }}</span><strong style="{{ $overdueCount > 0 ? 'color:#b3402e' : '' }}">{{ $overdueCount }}</strong></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'DUE THIS WEEK' : 'BU HAFTA SÜRESİ DOLUYOR' }}</span><strong style="{{ $dueThisWeekCount > 0 ? 'color:#8a6d1d' : '' }}">{{ $dueThisWeekCount }}</strong></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'RECERTIFICATION DUE' : 'YENİDEN SERTİFİKA GEREKİYOR' }}</span><strong style="{{ $recertificationDue->count() > 0 ? 'color:#b3402e' : '' }}">{{ $recertificationDue->count() }}</strong></div>
</div>
<div class="row row-cols-1 row-cols-md-2 g-4">
<div class="card metric-card"><span class="tag">{{ $en ? 'TEAM MEMBERS' : 'EKİP ÜYESİ' }}</span><strong>{{ $team->count() }}</strong><small>{{ $en ? 'Reporting to you directly' : 'Size doğrudan bağlı çalışanlar' }}@if($allReports->count() > $team->count()) · {{ $allReports->count() }} {{ $en ? 'in your whole organization' : 'toplam organizasyonunuzda' }}@endif</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'CERTIFICATES EARNED' : 'KAZANILAN SERTİFİKA' }}</span><strong>{{ $certificatesCount }}</strong><small>{{ $en ? "Your team's total" : 'Ekibinizin toplamı' }}</small></div>
</div>
</div>

{{-- ============ TEAM ============ --}}
<div data-tab-panel="team" hidden>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-1">
@forelse($team as $member)
<article class="card management-card">
<div class="course-card-top"><span class="badge">{{ $member->department ?: ($en ? 'No department' : 'Departman yok') }}</span></div>
<h3>{{ $member->name }}</h3>
<p class="muted">{{ $member->job_title ?: $member->email }}</p>
<div class="course-meta">
<span>{{ $member->enrollments_count }} {{ $en ? 'enrollments' : 'kayıt' }}</span>
<span>•</span>
<span>{{ $member->completed_enrollments_count }} {{ $en ? 'completed' : 'tamamlandı' }}</span>
@if($member->enrollments_count > 0)
<span>•</span>
<span>%{{ (int) round($member->completed_enrollments_count / $member->enrollments_count * 100) }}</span>
@endif
</div>
</article>
@empty
<div class="card empty-state">{{ $en ? 'No one reports to you yet. Ask an admin to assign team members in the admin panel.' : 'Şu an size bağlı bir çalışan yok. Bir yöneticiden admin panelinden ekip üyesi atamasını isteyin.' }}</div>
@endforelse
</div>
</div>

{{-- ============ TRAINING ============ --}}
<div data-tab-panel="training" hidden>
<div class="card mt-1">
@if($incompleteEnrollments->isEmpty())
<p class="muted" style="margin:0">{{ $en ? 'Nothing in progress right now.' : 'Şu an devam eden bir eğitim yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($incompleteEnrollments as $enrollment)
@php($overdue = $enrollment->course->isOverdueFor($enrollment))
@php($due = $enrollment->course->dueDateFor($enrollment))
@php($atRisk = ! $overdue && $due && $due->isBefore(now()->addDays(7)))
<li class="list-group-item requirement-row">
<span><b>{{ $enrollment->user->name }}</b> — {{ $enrollment->course->title }}@unless($directIds->contains($enrollment->user_id)) <small class="muted">({{ $en ? 'sub-team' : 'alt ekip' }})</small>@endunless</span>
<span>
@if($overdue)
<span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Overdue' : 'Süresi geçti' }}</span>
@elseif($atRisk)
<span class="badge" style="background:#fdf3df;color:#8a6d1d">{{ $en ? 'At risk' : 'Riskli' }}</span>
@else
<span class="badge">{{ $en ? 'In progress' : 'Devam ediyor' }}</span>
@endif
</span>
</li>
@endforeach
</ul>
@endif
</div>
</div>

{{-- ============ COMPLIANCE ============ --}}
<div data-tab-panel="compliance" hidden>
<div class="card mt-1">
@if($recertificationDue->isEmpty())
<p class="muted" style="margin:0">{{ $en ? 'No one needs recertification right now.' : 'Şu an yeniden sertifikalandırma gereken kimse yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($recertificationDue as $enrollment)
<li class="list-group-item requirement-row">
<span><b>{{ $enrollment->user->name }}</b> — {{ $enrollment->course->title }}@unless($directIds->contains($enrollment->user_id)) <small class="muted">({{ $en ? 'sub-team' : 'alt ekip' }})</small>@endunless</span>
<span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Expired '.$enrollment->certificate->expiresAt()->format('d.m.Y') : $enrollment->certificate->expiresAt()->format('d.m.Y').' tarihinde süresi doldu' }}</span>
</li>
@endforeach
</ul>
@endif
</div>
</div>

{{-- ============ REPORTS ============ --}}
<div data-tab-panel="reports" hidden>
<div class="card mt-1">
<h2>{{ $en ? 'Team report' : 'Ekip raporu' }}</h2>
<p class="muted">{{ $en ? 'A CSV export of every enrollment across your reporting chain -- status, completion, certificate.' : 'Raporlama zincirinizdeki her kaydın CSV çıktısı -- durum, tamamlanma, sertifika.' }}</p>
<a class="btn btn-primary" href="{{ route('manager.export') }}"><x-lucide name="download" size="14" /> {{ $en ? 'Download CSV' : 'CSV indir' }}</a>
</div>
</div>

<x-workspace-tab-script id="manager-workspace-tabs" />
@endsection

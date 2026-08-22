@extends('lms.manager-layout')
@section('page-title','Ekip genel bakış')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
<div class="card metric-card"><span class="tag">{{ $en ? 'TEAM MEMBERS' : 'EKİP ÜYESİ' }}</span><strong>{{ $team->count() }}</strong><small>{{ $en ? 'Reporting to you' : 'Size bağlı çalışanlar' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'IN PROGRESS' : 'DEVAM EDEN EĞİTİM' }}</span><strong>{{ $incompleteEnrollments->count() }}</strong><small>{{ $en ? 'Not yet completed' : 'Henüz tamamlanmamış kayıtlar' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'CERTIFICATES EARNED' : 'KAZANILAN SERTİFİKA' }}</span><strong>{{ $certificatesCount }}</strong><small>{{ $en ? "Your team's total" : 'Ekibinizin toplamı' }}</small></div>
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'TEAM' : 'EKİP' }}</span><h2>{{ $en ? 'Team members' : 'Ekip üyeleri' }}</h2></div></div>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
@forelse($team as $member)
<article class="card management-card">
<div class="course-card-top"><span class="status">{{ $member->department ?: ($en ? 'No department' : 'Departman yok') }}</span></div>
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

@if($incompleteEnrollments->isNotEmpty())
<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'FOLLOW-UP' : 'TAKİP' }}</span><h2>{{ $en ? 'Training in progress' : 'Devam eden eğitimler' }}</h2></div></div>
<div class="card">
@foreach($incompleteEnrollments as $enrollment)
<div class="requirement-row"><span><b>{{ $enrollment->user->name }}</b> — {{ $enrollment->course->title }}</span><span class="status">{{ ['enrolled' => $en ? 'Enrolled' : 'Kayıtlı', 'in_progress' => $en ? 'In progress' : 'Devam ediyor'][$enrollment->status] ?? $enrollment->status }}</span></div>
@endforeach
</div>
@endif
@endsection

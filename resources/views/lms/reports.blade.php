@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Enrollment report' : 'Kayıt raporu').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'REPORTS' : 'RAPORLAR' }}</span><h1>{{ $en ? 'Enrollment report' : 'Kayıt raporu' }}</h1><p class="muted">{{ $en ? 'Filter and export learner, course, status, completion and certificate data.' : 'Öğrenci, eğitim, durum, tamamlanma ve sertifika bilgilerini filtreleyip dışa aktarın.' }}</p></div></div>

<form method="get" action="{{ route('reports.index') }}" class="report-filters">
<div class="field"><label for="course_id">{{ $en ? 'Course' : 'Eğitim' }}</label><select id="course_id" name="course_id"><option value="">{{ $en ? 'All' : 'Tümü' }}</option>@foreach($courses as $course)<option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>{{ $course->title }}</option>@endforeach</select></div>
<div class="field"><label for="status">{{ $en ? 'Status' : 'Durum' }}</label><select id="status" name="status"><option value="">{{ $en ? 'All' : 'Tümü' }}</option><option value="enrolled" {{ request('status')==='enrolled'?'selected':'' }}>{{ $en ? 'Enrolled' : 'Kayıtlı' }}</option><option value="in_progress" {{ request('status')==='in_progress'?'selected':'' }}>{{ $en ? 'In progress' : 'Devam ediyor' }}</option><option value="completed" {{ request('status')==='completed'?'selected':'' }}>{{ $en ? 'Completed' : 'Tamamlandı' }}</option></select></div>
<div class="field"><label for="department">{{ $en ? 'Department' : 'Departman' }}</label><select id="department" name="department"><option value="">{{ $en ? 'All' : 'Tümü' }}</option>@foreach($departments as $department)<option value="{{ $department }}" {{ request('department')===$department ? 'selected' : '' }}>{{ $department }}</option>@endforeach</select></div>
<div class="field"><label for="organization_unit_id">{{ $en ? 'Organization unit' : 'Organizasyon birimi' }}</label><select id="organization_unit_id" name="organization_unit_id"><option value="">{{ $en ? 'All' : 'Tümü' }}</option>@foreach($organizationUnits as $unit)<option value="{{ $unit->id }}" {{ request('organization_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>@endforeach</select></div>
<div class="field"><label for="from">{{ $en ? 'From' : 'Başlangıç' }}</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div>
<div class="field"><label for="to">{{ $en ? 'To' : 'Bitiş' }}</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>
<div class="field" style="align-self:flex-end;display:flex;gap:8px">
<button type="submit" class="btn btn-primary">{{ $en ? 'Filter' : 'Filtrele' }}</button>
<a class="btn btn-outline-secondary" href="{{ route('reports.export', request()->query()) }}">{{ $en ? 'Download CSV' : 'CSV indir' }}</a>
</div>
</form>

<div class="card surface report-table-wrap">
<table class="report-table">
<thead><tr><th>{{ $en ? 'Learner' : 'Öğrenci' }}</th><th>{{ $en ? 'Department' : 'Departman' }}</th><th>{{ $en ? 'Course' : 'Eğitim' }}</th><th class="col-secondary">{{ $en ? 'Instructor' : 'Eğitmen' }}</th><th>{{ $en ? 'Status' : 'Durum' }}</th><th>%</th><th class="col-secondary">{{ $en ? 'Enrolled' : 'Kayıt' }}</th><th>{{ $en ? 'Completed' : 'Tamamlanma' }}</th><th class="col-secondary">{{ $en ? 'Certificate' : 'Sertifika' }}</th></tr></thead>
<tbody>
@forelse($enrollments as $enrollment)
<tr>
<td>{{ $enrollment->user->name }}</td>
<td>{{ $enrollment->user->department ?: '—' }}</td>
<td>{{ $enrollment->course->title }}</td>
<td class="col-secondary">{{ $enrollment->course->instructor->name }}</td>
<td><span class="badge">{{ ['enrolled'=>$en?'Enrolled':'Kayıtlı','in_progress'=>$en?'In progress':'Devam ediyor','completed'=>$en?'Completed':'Tamamlandı'][$enrollment->status] ?? $enrollment->status }}</span></td>
<td>%{{ $enrollment->completionStats()['percent'] }}</td>
<td class="col-secondary">{{ $enrollment->created_at->format('d.m.Y') }}</td>
<td>{{ $enrollment->completed_at?->format('d.m.Y') ?: '—' }}</td>
<td class="col-secondary">{{ $enrollment->certificate?->certificate_number ?: '—' }}</td>
</tr>
@empty
<tr><td colspan="9" class="muted" style="text-align:center;padding:24px">{{ $en ? 'No records match these filters.' : 'Filtrelerle eşleşen kayıt yok.' }}</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $enrollments->links('lms.partials.pagination') }}
@endsection

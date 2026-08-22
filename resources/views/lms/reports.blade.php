@extends('lms.layout')
@section('title','Kayıt raporu · Arucad Campus LMS')
@section('content')
<div class="section-heading"><div><span class="eyebrow">RAPORLAR</span><h1>Kayıt raporu</h1><p class="muted">Öğrenci, eğitim, durum, tamamlanma ve sertifika bilgilerini filtreleyip dışa aktarın.</p></div></div>

<form method="get" action="{{ route('reports.index') }}" class="report-filters">
<div class="field"><label for="course_id">Eğitim</label><select id="course_id" name="course_id"><option value="">Tümü</option>@foreach($courses as $course)<option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>{{ $course->title }}</option>@endforeach</select></div>
<div class="field"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option><option value="enrolled" {{ request('status')==='enrolled'?'selected':'' }}>Kayıtlı</option><option value="in_progress" {{ request('status')==='in_progress'?'selected':'' }}>Devam ediyor</option><option value="completed" {{ request('status')==='completed'?'selected':'' }}>Tamamlandı</option></select></div>
<div class="field"><label for="department">Departman</label><select id="department" name="department"><option value="">Tümü</option>@foreach($departments as $department)<option value="{{ $department }}" {{ request('department')===$department ? 'selected' : '' }}>{{ $department }}</option>@endforeach</select></div>
<div class="field"><label for="from">Başlangıç</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div>
<div class="field"><label for="to">Bitiş</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>
<div class="field" style="align-self:flex-end;display:flex;gap:8px">
<button type="submit">Filtrele</button>
<a class="button secondary" href="{{ route('reports.export', request()->query()) }}">CSV indir</a>
</div>
</form>

<div class="card surface report-table-wrap">
<table class="report-table">
<thead><tr><th>Öğrenci</th><th>Departman</th><th>Eğitim</th><th>Eğitmen</th><th>Durum</th><th>%</th><th>Kayıt</th><th>Tamamlanma</th><th>Sertifika</th></tr></thead>
<tbody>
@forelse($enrollments as $enrollment)
<tr>
<td>{{ $enrollment->user->name }}</td>
<td>{{ $enrollment->user->department ?: '—' }}</td>
<td>{{ $enrollment->course->title }}</td>
<td>{{ $enrollment->course->instructor->name }}</td>
<td><span class="status">{{ ['enrolled'=>'Kayıtlı','in_progress'=>'Devam ediyor','completed'=>'Tamamlandı'][$enrollment->status] ?? $enrollment->status }}</span></td>
<td>%{{ $enrollment->completionStats()['percent'] }}</td>
<td>{{ $enrollment->created_at->format('d.m.Y') }}</td>
<td>{{ $enrollment->completed_at?->format('d.m.Y') ?: '—' }}</td>
<td>{{ $enrollment->certificate?->certificate_number ?: '—' }}</td>
</tr>
@empty
<tr><td colspan="9" class="muted" style="text-align:center;padding:24px">Filtrelerle eşleşen kayıt yok.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $enrollments->links('lms.partials.pagination') }}
@endsection

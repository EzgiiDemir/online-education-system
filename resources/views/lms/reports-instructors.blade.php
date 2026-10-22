@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Instructor report' : 'Eğitmen raporu').' · '.__('Arucad Eğitim Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'REPORTS' : 'RAPORLAR' }}</span><h1>{{ $en ? 'Instructor report' : 'Eğitmen raporu' }}</h1><p class="muted">{{ $en ? 'Courses, students and average completion rate per instructor.' : 'Eğitmen başına eğitim, öğrenci ve ortalama tamamlanma oranı.' }}</p></div>
<a class="btn btn-outline-secondary" href="{{ route('reports.instructors.export') }}">{{ $en ? 'Download CSV' : 'CSV indir' }}</a>
</div>

@include('lms.partials.report-tabs', ['active' => 'instructors'])

<div class="card surface report-table-wrap">
<table class="report-table">
<thead><tr><th>{{ $en ? 'Instructor' : 'Eğitmen' }}</th><th class="col-secondary">{{ $en ? 'Email' : 'E-posta' }}</th><th>{{ $en ? 'Courses' : 'Eğitim sayısı' }}</th><th>{{ $en ? 'Students' : 'Öğrenci' }}</th><th>{{ $en ? 'Avg. completion' : 'Ortalama tamamlanma' }}</th></tr></thead>
<tbody>
@forelse($instructors as $row)
<tr>
<td>{{ $row['instructor']->name }}</td>
<td class="col-secondary">{{ $row['instructor']->email }}</td>
<td>{{ $row['course_count'] }}</td>
<td>{{ $row['student_count'] }}</td>
<td>{{ $row['avg_rate'] !== null ? '%'.$row['avg_rate'] : '—' }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted" style="text-align:center;padding:24px">{{ $en ? 'No instructors with courses yet.' : 'Eğitimi olan eğitmen yok.' }}</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection

@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Completion report' : 'Tamamlanma raporu').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'REPORTS' : 'RAPORLAR' }}</span><h1>{{ $en ? 'Completion report' : 'Tamamlanma raporu' }}</h1><p class="muted">{{ $en ? 'How each course as a whole is doing.' : 'Her eğitimin bütün olarak durumu.' }}</p></div>
<a class="btn btn-outline-secondary" href="{{ route('reports.completion.export') }}">{{ $en ? 'Download CSV' : 'CSV indir' }}</a>
</div>

@include('lms.partials.report-tabs', ['active' => 'completion'])

<div class="card surface report-table-wrap">
<table class="report-table">
<thead><tr><th>{{ $en ? 'Course' : 'Eğitim' }}</th><th class="col-secondary">{{ $en ? 'Instructor' : 'Eğitmen' }}</th><th>{{ $en ? 'Enrolled' : 'Kayıt' }}</th><th>{{ $en ? 'Completed' : 'Tamamlayan' }}</th><th>{{ $en ? 'Rate' : 'Oran' }}</th></tr></thead>
<tbody>
@forelse($courses as $row)
<tr>
<td>{{ $row['course']->title }}</td>
<td class="col-secondary">{{ $row['course']->instructor->name }}</td>
<td>{{ $row['total'] }}</td>
<td>{{ $row['completed'] }}</td>
<td>%{{ $row['rate'] }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted" style="text-align:center;padding:24px">{{ $en ? 'No courses yet.' : 'Henüz eğitim yok.' }}</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection

@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Attendance report' : 'Katılım raporu').' · '.(session('locale', 'tr') === 'en' ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'REPORTS' : 'RAPORLAR' }}</span><h1>{{ $en ? 'Attendance report' : 'Katılım raporu' }}</h1><p class="muted">{{ $en ? 'Average live session attendance per online course.' : 'Online eğitim başına ortalama canlı seans katılımı.' }}</p></div>
<a class="btn btn-outline-secondary" href="{{ route('reports.attendance.export') }}">{{ $en ? 'Download CSV' : 'CSV indir' }}</a>
</div>

@include('lms.partials.report-tabs', ['active' => 'attendance'])

<div class="card surface report-table-wrap">
<table class="report-table">
<thead><tr><th>{{ $en ? 'Course' : 'Eğitim' }}</th><th>{{ $en ? 'Sessions' : 'Seans sayısı' }}</th><th>{{ $en ? 'Avg. attendance' : 'Ortalama katılım' }}</th></tr></thead>
<tbody>
@forelse($courses as $row)
<tr>
<td>{{ $row['course']->title }}</td>
<td>{{ $row['session_count'] }}</td>
<td>{{ $row['avg_rate'] !== null ? '%'.$row['avg_rate'] : '—' }}</td>
</tr>
@empty
<tr><td colspan="3" class="muted" style="text-align:center;padding:24px">{{ $en ? 'No online courses with sessions yet.' : 'Seansı olan online eğitim yok.' }}</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection

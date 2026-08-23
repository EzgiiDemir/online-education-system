@extends('lms.instructor-layout')
@section('page-title', (session('locale', 'tr') === 'en' ? 'Activity log' : 'Erişim ve işlem kaydı').' · '.$course->title)
@section('content')
@php($en = session('locale', 'tr') === 'en')

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
<div class="card metric-card"><span class="tag">{{ $en ? 'CONTENT ITEMS' : 'İÇERİK ÖĞESİ' }}</span><strong>{{ $stats['total_content_items'] }}</strong><small>{{ $en ? 'Materials + activities' : 'Materyal + etkinlik' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'UNIQUE LEARNERS' : 'BENZERSİZ ÖĞRENCİ' }}</span><strong>{{ $stats['unique_learners'] }}</strong><small>{{ $en ? 'Accessed at least one item' : 'En az bir içeriğe erişti' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'COMPLETION RATE' : 'TAMAMLAMA ORANI' }}</span><strong>%{{ $stats['completion_rate'] }}</strong><small>{{ $stats['completed_views'] }} / {{ $stats['total_views'] }} {{ $en ? 'views completed' : 'erişim tamamlandı' }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'AVG. WATCH TIME' : 'ORT. İZLEME SÜRESİ' }}</span><strong>{{ $stats['avg_watched_minutes'] }} {{ $en ? 'min' : 'dk' }}</strong><small>{{ $en ? 'Per access record' : 'Erişim kaydı başına' }}</small></div>
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'ACCESS LOG' : 'ERİŞİM KAYDI' }}</span><h2>{{ $en ? 'Who accessed what' : 'Kim neye eriştişti' }}</h2><p class="muted" style="margin-top:4px">{{ $en ? 'Per-learner watch time and completion status for every material and activity in this course.' : 'Bu eğitimdeki her materyal ve etkinlik için öğrenci bazında izleme süresi ve tamamlanma durumu.' }}</p></div></div>
<div class="card surface report-table-wrap mb-5">
<table class="report-table">
<thead><tr><th>{{ $en ? 'Learner' : 'Öğrenci' }}</th><th>{{ $en ? 'Content' : 'İçerik' }}</th><th class="col-secondary">{{ $en ? 'Type' : 'Tür' }}</th><th>{{ $en ? 'Watched' : 'İzlenen süre' }}</th><th>{{ $en ? 'Status' : 'Durum' }}</th><th class="col-secondary">{{ $en ? 'First access' : 'İlk erişim' }}</th><th class="col-secondary">{{ $en ? 'Last access' : 'Son erişim' }}</th></tr></thead>
<tbody>
@forelse($accessLog as $row)
<tr>
<td>{{ $row['learner']->name }}<br><small class="muted">{{ $row['learner']->email }}</small></td>
<td>{{ $row['content_title'] }}</td>
<td class="col-secondary"><span class="badge">{{ $row['content_type'] }}</span></td>
<td>{{ gmdate('i:s', $row['watched_seconds']) }}</td>
<td>@if($row['completed'])<span class="badge" style="background:#eef1e9;color:var(--success-dark)"><x-lucide name="check" size="12" /> {{ $en ? 'Completed' : 'Tamamlandı' }}</span>@else<span class="badge">{{ $en ? 'In progress' : 'Devam ediyor' }}</span>@endif</td>
<td class="col-secondary">{{ $row['first_seen_at']?->format('d.m.Y H:i') ?: '—' }}</td>
<td class="col-secondary">{{ $row['last_seen_at']?->format('d.m.Y H:i') ?: '—' }}</td>
</tr>
@empty
<tr><td colspan="7" class="muted" style="text-align:center;padding:24px">{{ $en ? 'No one has accessed any content in this course yet.' : 'Bu eğitimdeki içeriklere henüz kimse erişmedi.' }}</td></tr>
@endforelse
</tbody>
</table>
</div>

<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'CHANGE HISTORY' : 'DEĞİŞİKLİK GEÇMİŞİ' }}</span><h2>{{ $en ? 'What was done' : 'Ne yapıldı' }}</h2><p class="muted" style="margin-top:4px">{{ $en ? 'Every create/edit/delete on this course, its sections, activities and materials, tracked from the moment audit logging was enabled.' : 'Bu eğitim, bölümleri, etkinlikleri ve materyalleri üzerinde yapılan her oluşturma/düzenleme/silme işlemi, denetim kaydı etkinleştirildiği andan itibaren izlenir.' }}</p></div></div>
<div class="card surface report-table-wrap">
<table class="report-table">
<thead><tr><th>{{ $en ? 'Who' : 'Kim' }}</th><th>{{ $en ? 'Action' : 'İşlem' }}</th><th>{{ $en ? 'Content' : 'İçerik' }}</th><th class="col-secondary">{{ $en ? 'Changes' : 'Değişiklikler' }}</th><th>{{ $en ? 'When' : 'Ne zaman' }}</th></tr></thead>
<tbody>
@forelse($auditTrail as $entry)
<tr>
<td>{{ $entry->actor->name ?? ($en ? 'System' : 'Sistem') }}</td>
<td><span class="badge" style="{{ $entry->action === 'deleted' ? 'background:#f9e5e1;color:#b3402e' : ($entry->action === 'created' ? 'background:#eef1e9;color:var(--success-dark)' : '') }}">{{ ['created' => $en ? 'Created' : 'Oluşturdu', 'updated' => $en ? 'Updated' : 'Güncelledi', 'deleted' => $en ? 'Deleted' : 'Sildi'][$entry->action] ?? $entry->action }}</span></td>
<td>{{ $entry->entity_label ?: '#'.$entry->entity_id }} <small class="muted">({{ $entry->entity_type }})</small></td>
<td class="col-secondary">
@if($entry->action === 'updated' && $entry->changes)
@foreach($entry->changes as $field => $change)
<div><b>{{ $field }}:</b> <span class="muted">{{ \Illuminate\Support\Str::limit(is_array($change) ? ($change['from'] ?? '—') : $change, 20) }} → {{ \Illuminate\Support\Str::limit(is_array($change) ? ($change['to'] ?? '—') : $change, 20) }}</span></div>
@endforeach
@else
<span class="muted">—</span>
@endif
</td>
<td class="col-secondary">{{ $entry->created_at->format('d.m.Y H:i') }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted" style="text-align:center;padding:24px">{{ $en ? 'No tracked changes yet.' : 'Henüz izlenen bir değişiklik yok.' }}</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection

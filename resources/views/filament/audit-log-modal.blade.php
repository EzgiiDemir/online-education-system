@if($entries->isEmpty())
<p style="color:#6b7280;font-size:13px;margin:0">Bu kayıt için henüz izlenen bir değişiklik yok.</p>
@else
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead>
<tr style="text-align:left">
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">Kim</th>
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">İşlem</th>
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">Değişiklikler</th>
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">Ne zaman</th>
</tr>
</thead>
<tbody>
@foreach($entries as $entry)
<tr>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6">{{ $entry->actor->name ?? 'Sistem' }}</td>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6">
<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;{{ $entry->action === 'deleted' ? 'background:#fee2e2;color:#b91c1c' : ($entry->action === 'created' ? 'background:#dcfce7;color:#15803d' : 'background:#f3f4f6;color:#374151') }}">{{ ['created' => 'Oluşturdu', 'updated' => 'Güncelledi', 'deleted' => 'Sildi'][$entry->action] ?? $entry->action }}</span>
</td>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6">
@if($entry->action === 'updated' && $entry->changes)
@foreach($entry->changes as $field => $change)
<div><b>{{ $field }}:</b> <span style="color:#6b7280">{{ \Illuminate\Support\Str::limit(is_array($change) ? ($change['from'] ?? '—') : $change, 20) }} → {{ \Illuminate\Support\Str::limit(is_array($change) ? ($change['to'] ?? '—') : $change, 20) }}</span></div>
@endforeach
@else
<span style="color:#9ca3af">—</span>
@endif
</td>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6;white-space:nowrap">{{ $entry->created_at->format('d.m.Y H:i') }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif

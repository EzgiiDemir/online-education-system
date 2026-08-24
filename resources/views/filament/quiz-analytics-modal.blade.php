@if($summary['total_attempts'] === 0)
<p style="color:#6b7280;font-size:13px;margin:0">Bu sınav için henüz gönderilmiş bir deneme yok.</p>
@else
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
<div style="padding:12px;border:1px solid #e5e7eb;border-radius:8px">
<div style="font-size:11px;color:#6b7280;text-transform:uppercase">Toplam deneme</div>
<div style="font-size:20px;font-weight:600">{{ $summary['total_attempts'] }}</div>
</div>
<div style="padding:12px;border:1px solid #e5e7eb;border-radius:8px">
<div style="font-size:11px;color:#6b7280;text-transform:uppercase">Katılan öğrenci</div>
<div style="font-size:20px;font-weight:600">{{ $summary['unique_learners'] }}</div>
</div>
<div style="padding:12px;border:1px solid #e5e7eb;border-radius:8px">
<div style="font-size:11px;color:#6b7280;text-transform:uppercase">Geçme oranı</div>
<div style="font-size:20px;font-weight:600">%{{ $summary['pass_rate'] }}</div>
</div>
<div style="padding:12px;border:1px solid #e5e7eb;border-radius:8px">
<div style="font-size:11px;color:#6b7280;text-transform:uppercase">Ortalama puan</div>
<div style="font-size:20px;font-weight:600">%{{ $summary['average_score'] }}</div>
</div>
</div>

<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead>
<tr style="text-align:left">
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">Soru</th>
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">Kaç denemede soruldu</th>
<th style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:11px;text-transform:uppercase">Doğru cevap oranı</th>
</tr>
</thead>
<tbody>
@foreach($summary['per_question'] as $row)
<tr>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6">{{ $row['question'] }}</td>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6">{{ $row['answered'] }}</td>
<td style="padding:8px 10px;border-bottom:1px solid #f3f4f6">
@if($row['correct_rate'] === null)
<span style="color:#9ca3af">—</span>
@else
<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;{{ $row['correct_rate'] >= 70 ? 'background:#dcfce7;color:#15803d' : ($row['correct_rate'] >= 40 ? 'background:#fef9c3;color:#a16207' : 'background:#fee2e2;color:#b91c1c') }}">%{{ $row['correct_rate'] }}</span>
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endif

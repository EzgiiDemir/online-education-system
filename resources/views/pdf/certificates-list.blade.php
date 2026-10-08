<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans;font-size:11px;color:#172033;margin:0;padding:24px}
h1{font-size:18px;color:#4763e6;margin:0 0 4px}
p.meta{color:#667;margin:0 0 18px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:6px 8px;border-bottom:1px solid #e4e7ec}
th{background:#f2f4f7;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
</style></head><body>
<h1>Arucad Eğitim Merkezi — Sertifikalar</h1>
<p class="meta">Oluşturulma tarihi: {{ now()->format('d.m.Y H:i') }} · Toplam {{ $rows->count() }} sertifika</p>
<table>
<thead><tr><th>Sertifika No</th><th>Sahibi</th><th>Eğitim</th><th>Veriliş Tarihi</th><th>Durum</th></tr></thead>
<tbody>
@foreach($rows as $certificate)
<tr>
<td>{{ $certificate->certificate_number }}</td>
<td>{{ $certificate->enrollment->user->name }}</td>
<td>{{ $certificate->enrollment->course->title }}</td>
<td>{{ $certificate->issued_at->format('d.m.Y') }}</td>
<td>{{ ['active' => 'Geçerli', 'expired' => 'Süresi doldu', 'revoked' => 'İptal edildi'][$certificate->status()] ?? $certificate->status() }}</td>
</tr>
@endforeach
</tbody>
</table>
</body></html>

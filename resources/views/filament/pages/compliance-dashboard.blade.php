<x-filament-panels::page>
    @php($m = $this->getMetrics())

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Zorunlu / Uyumluluk kaydı</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px">{{ $m['total'] }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">{{ $m['completed'] }} tamamlandı{{ $m['completion_rate'] !== null ? ' (%'.$m['completion_rate'].')' : '' }}</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Süresi geçmiş</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;color:{{ $m['overdue'] > 0 ? '#b91c1c' : 'inherit' }}">{{ $m['overdue'] }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">Son tarihi geçen kayıtlar</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Muaf</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px">{{ $m['exempt'] }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">Muafiyet tanınan kayıtlar</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Sertifika süresi doldu</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;color:{{ $m['expired_certificates'] > 0 ? '#b91c1c' : 'inherit' }}">{{ $m['expired_certificates'] }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">Yeniden sertifikalandırma gerekiyor</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">30 gün içinde dolacak</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;color:{{ $m['expiring_soon_certificates'] > 0 ? '#a16207' : 'inherit' }}">{{ $m['expiring_soon_certificates'] }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">Sertifika süresi yakında doluyor</div>
        </div>
    </div>

    <div style="margin-top:28px">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:12px">Eğitime göre uyumluluk durumu</h2>
        @php($breakdown = $this->getCourseBreakdown())
        @if($breakdown->isEmpty())
            <p style="font-size:13px;opacity:.6">Zorunlu veya uyumluluk kategorisinde eğitim yok.</p>
        @else
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="text-align:left">
                        <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Eğitim</th>
                        <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Kayıt</th>
                        <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Tamamlanma</th>
                        <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Süresi geçmiş</th>
                        <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Muaf</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($breakdown as $row)
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">
                            {{ $row['course']->title }}
                            <span style="font-size:11px;opacity:.6">({{ ['required' => 'Zorunlu', 'compliance' => 'Uyumluluk'][$row['course']->category] ?? $row['course']->category }})</span>
                        </td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $row['total'] }}</td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $row['completed'] }}{{ $row['completion_rate'] !== null ? ' (%'.$row['completion_rate'].')' : '' }}</td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06);color:{{ $row['overdue'] > 0 ? '#b91c1c' : 'inherit' }}">{{ $row['overdue'] }}</td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $row['exempt'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>

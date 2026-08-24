<x-filament-panels::page>
    @php($m = $this->getMetrics())

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Veritabanı</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;color:{{ $m['db_ok'] ? '#15803d' : '#b91c1c' }}">{{ $m['db_ok'] ? 'Bağlı' : 'Bağlanamıyor' }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">{{ $m['db_driver'] }}{{ $m['db_error'] ? ' — '.$m['db_error'] : '' }}</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Kuyruk ({{ $m['queue_connection'] }})</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px">{{ $m['queue_pending'] }} bekleyen</div>
            <div style="font-size:12px;margin-top:4px;color:{{ $m['queue_failed'] > 0 ? '#b91c1c' : 'inherit' }};opacity:{{ $m['queue_failed'] > 0 ? '1' : '.7' }}">{{ $m['queue_failed'] }} başarısız iş</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Zamanlayıcı (scheduler)</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;color:{{ $m['scheduler_healthy'] ? '#15803d' : '#b91c1c' }}">{{ $m['scheduler_healthy'] ? 'Çalışıyor' : 'Tespit edilemedi' }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">{{ $m['scheduler_last_run'] ? 'Son nabız: '.\Illuminate\Support\Carbon::parse($m['scheduler_last_run'])->diffForHumans() : 'Hiç çalışmadı' }}</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">E-posta</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px">{{ $m['mail_mailer'] }}</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">{{ $m['mail_mailer'] === 'log' ? 'Gerçekten gönderilmiyor, log dosyasına yazılıyor' : 'Gerçek e-posta gönderimi aktif' }}</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Depolama diski</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px">{{ $m['disk_free_gb'] ?? '—' }} GB boş</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">{{ $m['disk_total_gb'] ?? '—' }} GB toplam</div>
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">Bugün</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;color:{{ $m['error_count_today'] > 0 ? '#b91c1c' : 'inherit' }}">{{ $m['error_count_today'] }} hata</div>
            <div style="font-size:12px;opacity:.7;margin-top:4px">{{ $m['audit_events_today'] }} denetim olayı</div>
        </div>
    </div>

    <div style="margin-top:28px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h2 style="font-size:16px;font-weight:700">Başarısız işler (son 20)</h2>
            @if($m['queue_failed'] > 0)
                <button type="button" wire:click="retryAllFailedJobs" style="font-size:12px;font-weight:600;padding:6px 12px;border-radius:999px;border:1px solid rgba(0,0,0,.15);cursor:pointer">Tümünü yeniden dene</button>
            @endif
        </div>
        @php($failedJobs = $this->getFailedJobs())
        @if($failedJobs->isEmpty())
            <p style="font-size:13px;opacity:.6">Başarısız iş yok.</p>
        @else
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="text-align:left"><th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">İş</th><th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Bağlantı/Kuyruk</th><th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Başarısız oldu</th><th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)"></th></tr>
                </thead>
                <tbody>
                    @foreach($failedJobs as $job)
                    @php($payload = json_decode($job->payload, true))
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $payload['displayName'] ?? '—' }}</td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $job->connection }} / {{ $job->queue }}</td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->format('d.m.Y H:i') }}</td>
                        <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">
                            <button type="button" wire:click="retryFailedJob('{{ $job->uuid }}')" style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;border:1px solid rgba(0,0,0,.15);cursor:pointer;margin-right:6px">Yeniden dene</button>
                            <button type="button" wire:click="deleteFailedJob('{{ $job->uuid }}')" style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;border:1px solid #b91c1c;color:#b91c1c;cursor:pointer">Sil</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6;margin-bottom:12px">Departmanlar</div>
            @php($departments = $this->getDepartments())
            @if($departments->isEmpty())
                <p style="font-size:13px;opacity:.6;margin:0">Henüz departman yok. Sağdaki formda "Departman" alanının yanındaki + ile ilk departmanı ekleyebilirsiniz.</p>
            @else
                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px">
                    @foreach($departments as $department)
                        <li style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border-radius:.5rem;background:rgba(0,0,0,.03);font-size:13px">
                            <span>
                                @if($department->parent)
                                    <span style="opacity:.5">{{ $department->parent->name }} / </span>
                                @endif
                                {{ $department->name }}
                            </span>
                            <span style="font-size:11px;font-weight:600;opacity:.6;white-space:nowrap">{{ $department->users_count }} kişi</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:20px">
            <form wire:submit="create">
                {{ $this->form }}

                <div style="margin-top:20px">
                    <x-filament::button type="submit">
                        Kullanıcı Oluştur
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
        <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:16px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6;margin-bottom:12px">Departmanlar</div>
            @php($departments = $this->getDepartments())
            @if($departments->isEmpty())
                <p style="font-size:13px;opacity:.6;margin:0">Henüz departman yok. Sağdaki formda "Departman" alanının yanındaki + ile ilk departmanı ekleyebilirsiniz.</p>
            @else
                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px">
                    <li>
                        <button type="button" wire:click="selectDepartment(null)" style="width:100%;text-align:left;border:0;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border-radius:.5rem;font-size:13px;background:{{ $selectedDepartmentId === null ? 'rgba(77,103,135,.12)' : 'transparent' }};color:{{ $selectedDepartmentId === null ? '#4D6787' : 'inherit' }};font-weight:{{ $selectedDepartmentId === null ? '600' : '400' }}">
                            <span>Tüm departmanlar</span>
                        </button>
                    </li>
                    @foreach($departments as $department)
                        <li>
                            <button type="button" wire:click="selectDepartment({{ $department->id }})" style="width:100%;text-align:left;border:0;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border-radius:.5rem;font-size:13px;background:{{ $selectedDepartmentId === $department->id ? 'rgba(77,103,135,.12)' : 'rgba(0,0,0,.03)' }};color:{{ $selectedDepartmentId === $department->id ? '#4D6787' : 'inherit' }};font-weight:{{ $selectedDepartmentId === $department->id ? '600' : '400' }}">
                                <span>
                                    @if($department->parent)
                                        <span style="opacity:.5">{{ $department->parent->name }} / </span>
                                    @endif
                                    {{ $department->name }}
                                </span>
                                <span style="font-size:11px;font-weight:600;opacity:.6;white-space:nowrap">{{ $department->users_count }} kişi</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:20px">
            @if($department = $this->getSelectedDepartment())
                @php($counts = $this->getSelectedDepartmentRoleCounts())
                <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:20px">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6">
                        @if($department->parent){{ $department->parent->name }} / @endif
                        Departman
                    </div>
                    <h2 style="font-size:20px;font-weight:700;margin:4px 0 16px">{{ $department->name }}</h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:12px">
                        <div><div style="font-size:20px;font-weight:700">{{ $counts['total'] }}</div><small style="opacity:.6">Toplam üye</small></div>
                        <div><div style="font-size:20px;font-weight:700">{{ $counts['manager'] }}</div><small style="opacity:.6">Yönetici</small></div>
                        <div><div style="font-size:20px;font-weight:700">{{ $counts['instructor'] }}</div><small style="opacity:.6">Eğitmen</small></div>
                        <div><div style="font-size:20px;font-weight:700">{{ $counts['learner'] }}</div><small style="opacity:.6">Öğrenci</small></div>
                    </div>

                    @php($members = $this->getSelectedDepartmentMembers())
                    @if($members->isNotEmpty())
                    <div style="margin-top:20px;overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="text-align:left">
                                    <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Ad soyad</th>
                                    <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">E-posta</th>
                                    <th style="padding:8px;border-bottom:1px solid rgba(0,0,0,.1)">Rol</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($members as $member)
                                <tr>
                                    <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $member->name }}</td>
                                    <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $member->email }}</td>
                                    <td style="padding:8px;border-bottom:1px solid rgba(0,0,0,.06)">{{ ['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici', 'admin' => 'Admin'][$member->role] ?? $member->role }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            @endif

            <div class="fi-section-content-ctn" style="border:1px solid rgba(0,0,0,.1);border-radius:.75rem;padding:20px">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6;margin-bottom:12px">
                    {{ $department ?? false ? $department->name.' departmanına ekle' : 'Kullanıcı ekle' }}
                </div>
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
    </div>
</x-filament-panels::page>

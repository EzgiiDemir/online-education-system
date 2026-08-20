<?php

namespace App\Filament\Resources\PortalSettings\Pages;

use App\Filament\Resources\PortalSettings\PortalSettingResource;
use App\Models\PortalSetting;
use Filament\Resources\Pages\ListRecords;

/**
 * Portal ayarları tek bir kaydın (PortalSetting::current(), id=1) üzerinde
 * çalışır. Standart bir liste sayfası, adminin yanlışlıkla ikinci bir
 * ayar kaydı oluşturmasına ya da bu tek kaydı silmesine izin verirdi;
 * bunun yerine listeye hiç girmeden doğrudan düzenleme formuna yönlendirir.
 */
class ListPortalSettings extends ListRecords
{
    protected static string $resource = PortalSettingResource::class;

    public function mount(): void
    {
        $this->redirect(PortalSettingResource::getUrl('edit', ['record' => PortalSetting::current()->id]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

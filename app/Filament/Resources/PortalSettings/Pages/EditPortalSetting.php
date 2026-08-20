<?php

namespace App\Filament\Resources\PortalSettings\Pages;

use App\Filament\Resources\PortalSettings\PortalSettingResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Silme aksiyonu kasıtlı olarak yok: bu, tüm portalın tek ayar kaydı
 * (PortalSetting::current(), id=1). Silinirse Entra ID/domain politikası/
 * marka ayarları sessizce varsayılana döner (bkz. PortalSetting::current()).
 */
class EditPortalSetting extends EditRecord
{
    protected static string $resource = PortalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

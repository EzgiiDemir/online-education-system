<?php

namespace App\Filament\Resources\PortalSettings;

use App\Filament\Resources\PortalSettings\Pages\EditPortalSetting;
use App\Filament\Resources\PortalSettings\Pages\ListPortalSettings;
use App\Filament\Resources\PortalSettings\Schemas\PortalSettingForm;
use App\Filament\Resources\PortalSettings\Tables\PortalSettingsTable;
use App\Models\PortalSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PortalSettingResource extends Resource
{
    protected static ?string $model = PortalSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Portal ayarları';

    public static function form(Schema $schema): Schema
    {
        return PortalSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortalSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalSettings::route('/'),
            'edit' => EditPortalSetting::route('/{record}/edit'),
        ];
    }
}

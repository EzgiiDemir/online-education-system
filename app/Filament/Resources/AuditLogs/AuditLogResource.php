<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Denetim günlüğü';

    protected static ?string $modelLabel = 'denetim kaydı';

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('actor.name')->label('Kim')->placeholder('Sistem'),
                TextEntry::make('action')->label('İşlem')->badge()
                    ->formatStateUsing(fn (string $state) => ['created' => 'Oluşturuldu', 'updated' => 'Güncellendi', 'deleted' => 'Silindi'][$state] ?? $state),
                TextEntry::make('entity_type')->label('Kayıt türü'),
                TextEntry::make('entity_label')->label('Kayıt')->placeholder('—'),
                TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                TextEntry::make('changes')->label('Değişiklikler')->columnSpanFull()
                    ->html()
                    ->formatStateUsing(fn ($state) => is_array($state) && $state !== [] ? '<pre style="white-space:pre-wrap;font-size:12px">'.e(json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre>' : '—'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}

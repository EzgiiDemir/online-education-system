<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('actor.name')->label('Kim')->placeholder('Sistem')->searchable(),
                TextColumn::make('action')
                    ->label('İşlem')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['created' => 'Oluşturuldu', 'updated' => 'Güncellendi', 'deleted' => 'Silindi'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('entity_type')->label('Kayıt türü'),
                TextColumn::make('entity_label')->label('Kayıt')->placeholder('—')->searchable(),
                TextColumn::make('changes')
                    ->label('Değişiklik')
                    ->formatStateUsing(fn ($state) => is_array($state) && $state !== [] ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('ip_address')->label('IP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('İşlem')->options(['created' => 'Oluşturuldu', 'updated' => 'Güncellendi', 'deleted' => 'Silindi']),
                SelectFilter::make('entity_type')->label('Kayıt türü')->options(fn () => \App\Models\AuditLog::query()->distinct()->pluck('entity_type', 'entity_type')->toArray()),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

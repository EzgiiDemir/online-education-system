<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
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
                TextColumn::make('created_at')->label(__('Zaman'))->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('actor.name')->label(__('Kim'))->placeholder(__('Sistem'))->searchable(),
                TextColumn::make('action')
                    ->label(__('İşlem'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['created' => 'Oluşturuldu', 'updated' => 'Güncellendi', 'deleted' => 'Silindi'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('entity_type')->label(__('Kayıt türü')),
                TextColumn::make('entity_label')->label(__('Kayıt'))->placeholder(__('—'))->searchable(),
                TextColumn::make('changes')
                    ->label(__('Değişiklik'))
                    ->formatStateUsing(fn ($state) => is_array($state) && $state !== [] ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('ip_address')->label(__('IP'))->placeholder(__('—'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('İşlem'))->options(['created' => 'Oluşturuldu', 'updated' => 'Güncellendi', 'deleted' => 'Silindi']),
                SelectFilter::make('entity_type')->label(__('Kayıt türü'))->options(fn () => \App\Models\AuditLog::query()->distinct()->pluck('entity_type', 'entity_type')->toArray()),
            ])
            ->recordActions([
                ViewAction::make()->label(__('Ayrıntı')),
            ])
            ->toolbarActions([]);
    }
}

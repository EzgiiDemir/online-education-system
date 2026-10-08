<?php

namespace App\Filament\Resources\ApiTokens\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Token adı'))->searchable(),
                TextColumn::make('last_used_at')->label(__('Son kullanım'))->dateTime('d.m.Y H:i')->placeholder(__('Hiç kullanılmadı')),
                TextColumn::make('created_at')->label(__('Oluşturulma'))->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()->label(__('İptal et')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

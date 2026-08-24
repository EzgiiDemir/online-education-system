<?php

namespace App\Filament\Resources\ScimTokens\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScimTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Token adı')->searchable(),
                TextColumn::make('last_used_at')->label('Son kullanım')->dateTime('d.m.Y H:i')->placeholder('Hiç kullanılmadı'),
                TextColumn::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()->label('İptal et'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

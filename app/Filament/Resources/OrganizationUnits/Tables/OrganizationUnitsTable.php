<?php

namespace App\Filament\Resources\OrganizationUnits\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganizationUnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Birim adı'))->searchable()->sortable(),
                TextColumn::make('parent.name')->label(__('Üst birim'))->placeholder(__('— (en üst seviye)')),
                TextColumn::make('users_count')->label(__('Doğrudan üye'))->counts('users')->badge(),
                TextColumn::make('children_count')->label(__('Alt birim'))->counts('children')->badge()->toggleable(),
                TextColumn::make('created_at')->label(__('Oluşturulma'))->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAuditLogAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\PortalSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('portal_name')->label(__('Portal')), TextColumn::make('accepted_domains')->label(__('Domain politikası'))->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state), TextColumn::make('entra_enabled')->label(__('Entra'))->badge(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}

<?php

namespace App\Filament\Resources\Materials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.course.title')->label('Eğitim')->searchable()->sortable(),
                TextColumn::make('session.title')->label('Seans')->searchable(),
                TextColumn::make('title')->label('Materyal')->searchable(),
                TextColumn::make('type')->label('Tür')->badge(),
                TextColumn::make('duration_seconds')->label('Süre (sn)')->placeholder('Belirsiz'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course_session_id')->label('Seans')->relationship('session', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

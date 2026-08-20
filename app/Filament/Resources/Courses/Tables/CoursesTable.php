<?php

namespace App\Filament\Resources\Courses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instructor.name')->label('Eğitmen')->searchable()->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('delivery_type')
                    ->searchable(),
                TextColumn::make('language')->label('Dil')->badge()->formatStateUsing(fn ($state) => $state === 'en' ? 'English' : 'Türkçe'),
                TextColumn::make('status')
                    ->badge()->searchable(),
                TextColumn::make('guest_instructor_name')->label('Misafir eğitimci')->placeholder('Yok')->toggleable(),
                TextColumn::make('certificate_enabled')->label('Sertifika')->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Pasif')->badge(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

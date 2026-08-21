<?php

namespace App\Filament\Resources\LearningPaths\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LearningPathsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Yol adı')->searchable()->sortable(),
                TextColumn::make('courses_count')->label('Eğitim sayısı')->counts('courses')->badge(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Taslak', 'published' => 'Yayında'][$state] ?? $state)
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'warning'),
                TextColumn::make('is_sequential')->label('Sıralı')->formatStateUsing(fn ($state) => $state ? 'Evet' : 'Hayır')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(['draft' => 'Taslak', 'published' => 'Yayında']),
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

<?php

namespace App\Filament\Resources\LearningPaths\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LearningPathsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('Yol adı'))->searchable()->sortable(),
                TextColumn::make('courses_count')->label(__('Eğitim sayısı'))->counts('courses')->badge(),
                TextColumn::make('status')->label(__('Durum'))->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Taslak', 'published' => 'Yayında'][$state] ?? $state)
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'warning'),
                TextColumn::make('is_sequential')->label(__('Sıralı'))->formatStateUsing(fn ($state) => $state ? 'Evet' : 'Hayır')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Durum'))->options(['draft' => 'Taslak', 'published' => 'Yayında']),
            ])
            ->recordActions([
                ViewAction::make(),
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

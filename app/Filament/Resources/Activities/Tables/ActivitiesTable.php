<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Filament\Actions\QuizAnalyticsAction;
use App\Filament\Actions\ViewAuditLogAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('section.course.title')->label(__('Eğitim'))->searchable()->sortable(),
                TextColumn::make('section.title')->label(__('Bölüm'))->searchable(),
                TextColumn::make('title')->label(__('Etkinlik'))
                    ->searchable(),
                TextColumn::make('type')->label(__('Tür'))->badge()
                    ->searchable(),
                TextColumn::make('duration_seconds')->label(__('Süre (sn)'))->placeholder(__('Belirsiz'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sort_order')->label(__('Sıra'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_required')->label(__('Zorunlu'))
                    ->boolean(),
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
                SelectFilter::make('type')->label(__('Tür'))->options(['video' => 'Video', 'document' => 'Doküman', 'presentation' => 'Sunum', 'live_session' => 'Canlı oturum', 'resource' => 'Kaynak', 'quiz' => 'Sınav']),
            ])
            ->recordActions([
                Action::make('preview')->label(__('Önizle'))->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn ($record) => $record->path ? asset('storage/'.$record->path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => (bool) $record->path),
                EditAction::make(),
                QuizAnalyticsAction::make(),
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

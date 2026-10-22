<?php

namespace App\Filament\Resources\Materials\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use App\Support\ProtectedFiles;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.course.title')->label(__('Eğitim'))->searchable()->sortable(),
                TextColumn::make('session.title')->label(__('Seans'))->searchable(),
                TextColumn::make('title')->label(__('Materyal'))->searchable(),
                TextColumn::make('type')->label(__('Tür'))->badge(),
                TextColumn::make('duration_seconds')->label(__('Süre (sn)'))->placeholder(__('Belirsiz')),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course_session_id')->label(__('Seans'))->relationship('session', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('preview')->label(__('Önizle'))->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn ($record) => ProtectedFiles::url($record->path))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => (bool) $record->path),
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

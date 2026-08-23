<?php

namespace App\Filament\Resources\Materials\Tables;

use App\Filament\Actions\ViewAuditLogAction;
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
                Action::make('preview')->label('Önizle')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn ($record) => $record->path ? asset('storage/'.$record->path) : null)
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

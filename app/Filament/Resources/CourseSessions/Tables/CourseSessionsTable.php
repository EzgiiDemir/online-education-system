<?php

namespace App\Filament\Resources\CourseSessions\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.title')
                    ->label('Eğitim')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Seans')
                    ->searchable(),
                TextColumn::make('meeting_status')->label('Durum')->badge()->placeholder('Yüz yüze'),
                TextColumn::make('materials_count')->label('Materyal')->counts('materials')->badge(),
                TextColumn::make('starts_at')
                    ->label('Başlangıç')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Bitiş')
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
                SelectFilter::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload(),
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

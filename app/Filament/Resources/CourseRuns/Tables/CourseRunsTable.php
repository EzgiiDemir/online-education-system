<?php

namespace App\Filament\Resources\CourseRuns\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.title')
                    ->label(__('Eğitim'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Dönem adı'))
                    ->searchable(),
                TextColumn::make('code')
                    ->label(__('Kod'))
                    ->searchable(),
                TextColumn::make('enrollments_count')->label(__('Kayıt'))->counts('enrollments')->badge(),
                TextColumn::make('max_seats')->label(__('Kontenjan'))->placeholder(__('Sınırsız')),
                TextColumn::make('waitlist_entries_count')->label(__('Bekleme listesi'))->counts('waitlistEntries')->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->label(__('Durum'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'open', 'running' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('starts_at')
                    ->label(__('Başlangıç'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('Bitiş'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course_id')->label(__('Eğitim'))->relationship('course', 'title')->searchable()->preload(),
                SelectFilter::make('status')->label(__('Durum'))->options(['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi']),
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

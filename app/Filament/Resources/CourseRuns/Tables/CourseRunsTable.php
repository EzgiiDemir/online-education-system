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
                    ->label('Eğitim')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Dönem adı')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Kod')
                    ->searchable(),
                TextColumn::make('enrollments_count')->label('Kayıt')->counts('enrollments')->badge(),
                TextColumn::make('max_seats')->label('Kontenjan')->placeholder('Sınırsız'),
                TextColumn::make('waitlist_entries_count')->label('Bekleme listesi')->counts('waitlistEntries')->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'open', 'running' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
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
            ])
            ->filters([
                SelectFilter::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload(),
                SelectFilter::make('status')->label('Durum')->options(['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi']),
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

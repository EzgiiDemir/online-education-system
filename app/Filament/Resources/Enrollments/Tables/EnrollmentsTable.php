<?php

namespace App\Filament\Resources\Enrollments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Katılımcı')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.title')
                    ->label('Eğitim')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('run.name')->label('Dönem')->placeholder('—')->toggleable(),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('completed_at')
                    ->label('Tamamlanma')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Kayıt tarihi')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload(),
                SelectFilter::make('status')->label('Durum')->options(['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı']),
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

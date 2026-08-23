<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')->label('')->circular()->disk('public')->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?background=4D6787&color=fff&name='.urlencode($record->name)),
                TextColumn::make('name')
                    ->label('Ad soyad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['admin' => 'Sistem Yöneticisi', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'learner' => 'Öğrenci'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'instructor' => 'warning',
                        'manager' => 'info',
                        default => 'success',
                    })
                    ->searchable(),
                TextColumn::make('manager.name')->label('Yöneticisi')->placeholder('—')->toggleable(),
                TextColumn::make('enrollments_count')->label('Kayıtlar')->counts('enrollments')->badge(),
                TextColumn::make('courses_count')->label('Verdiği kurslar')->counts('courses')->badge()->toggleable(),
                TextColumn::make('created_at')
                    ->label('Katılım')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'admin' => 'Sistem Yöneticisi']),
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

<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')->label(__(''))->circular()->disk('public')->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?background=4D6787&color=fff&name='.urlencode($record->name)),
                TextColumn::make('name')
                    ->label(__('Ad soyad'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('E-posta'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('Rol'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['admin' => 'Sistem Yöneticisi', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'learner' => 'Öğrenci'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'instructor' => 'warning',
                        'manager' => 'info',
                        default => 'success',
                    })
                    ->searchable(),
                TextColumn::make('manager.name')->label(__('Yöneticisi'))->placeholder(__('—'))->toggleable(),
                TextColumn::make('organizationUnit.name')->label(__('Organizasyon birimi'))->placeholder(__('—'))->toggleable(),
                TextColumn::make('enrollments_count')->label(__('Kayıtlar'))->counts('enrollments')->badge(),
                TextColumn::make('courses_count')->label(__('Verdiği kurslar'))->counts('courses')->badge()->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Katılım'))
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('Rol'))->options(['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'admin' => 'Sistem Yöneticisi']),
                SelectFilter::make('organization_unit_id')->label(__('Organizasyon birimi'))->relationship('organizationUnit', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ViewAuditLogAction::make(),
                DeleteAction::make()->before(function (DeleteAction $action, User $record) {
                    if ($reason = $record->blockingRemovalReason(isDelete: true)) {
                        Notification::make()->danger()->title($reason)->send();
                        $action->halt();
                    }
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->before(function (DeleteBulkAction $action, Collection $records) {
                        foreach ($records as $record) {
                            if ($reason = $record->blockingRemovalReason(isDelete: true)) {
                                Notification::make()->danger()->title($record->name.': '.$reason)->send();
                                $action->halt();
                            }
                        }
                    }),
                ]),
            ]);
    }
}

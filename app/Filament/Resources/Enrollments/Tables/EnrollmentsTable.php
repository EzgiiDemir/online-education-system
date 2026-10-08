<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Katılımcı'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.title')
                    ->label(__('Eğitim'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('run.name')->label(__('Dönem'))->placeholder(__('—'))->toggleable(),
                TextColumn::make('status')
                    ->label(__('Durum'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('completed_at')
                    ->label(__('Tamamlanma'))
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('exempted_at')
                    ->label(__('Muaf'))
                    ->boolean()
                    ->getStateUsing(fn (Enrollment $record) => $record->isExempt()),
                TextColumn::make('created_at')
                    ->label(__('Kayıt tarihi'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course_id')->label(__('Eğitim'))->relationship('course', 'title')->searchable()->preload(),
                SelectFilter::make('status')->label(__('Durum'))->options(['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı']),
                TernaryFilter::make('exempted_at')->label(__('Muaf'))->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('exempt')->label(__('Muaf tut'))->icon(Heroicon::OutlinedShieldCheck)->color('warning')
                    ->visible(fn (Enrollment $record) => ! $record->isExempt() && $record->status !== 'completed')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->label(__('Muafiyet gerekçesi'))->required()->maxLength(255),
                    ])
                    ->action(function (Enrollment $record, array $data) {
                        $record->exempt(Auth::user(), $data['reason']);
                        Notification::make()->title(__('Kayıt muaf tutuldu'))->success()->send();
                    }),
                Action::make('unexempt')->label(__('Muafiyeti kaldır'))->icon(Heroicon::OutlinedXCircle)->color('gray')
                    ->visible(fn (Enrollment $record) => $record->isExempt())
                    ->requiresConfirmation()
                    ->action(function (Enrollment $record) {
                        $record->unexempt();
                        Notification::make()->title(__('Muafiyet kaldırıldı'))->success()->send();
                    }),
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

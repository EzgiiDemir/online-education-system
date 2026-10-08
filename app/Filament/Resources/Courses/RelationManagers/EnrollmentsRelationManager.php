<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\TrainingAssigned;
use App\Services\EnrollmentService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Katılımcılar';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->label(__('Katılımcı'))->relationship('user', 'name')->searchable()->preload()->required(),
                Select::make('course_run_id')->label(__('Dönem (opsiyonel)'))->relationship('run', 'name')->searchable()->preload(),
                Select::make('status')->label(__('Durum'))->options(['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'])->default('enrolled')->required()->native(false),
                DateTimePicker::make('completed_at')->label(__('Tamamlanma tarihi')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')->label(__('Katılımcı'))->searchable(),
                TextColumn::make('run.name')->label(__('Dönem'))->placeholder(__('—')),
                TextColumn::make('status')->label(__('Durum'))->badge()
                    ->formatStateUsing(fn (string $state) => ['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('completed_at')->label(__('Tamamlanma'))->dateTime('d.m.Y H:i')->placeholder(__('—')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('bulkAssign')
                    ->label(__('Toplu ata'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('gray')
                    ->schema([
                        // Excludes only users whose current cycle is still
                        // active (not stale) -- someone whose certificate
                        // has expired is exactly who this action should be
                        // able to re-assign, not someone the picker hides.
                        Select::make('user_ids')->label(__('Katılımcılar'))
                            ->options(function () {
                                $course = $this->getOwnerRecord();
                                $activeUserIds = $course->currentEnrollments()->get()->reject->isStale()->pluck('user_id');

                                return User::whereNull('deactivated_at')->whereNotIn('id', $activeUserIds)->pluck('name', 'id');
                            })
                            ->multiple()
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $course = $this->getOwnerRecord();
                        $assigned = 0;
                        foreach (User::whereIn('id', $data['user_ids'])->get() as $user) {
                            $result = app(EnrollmentService::class)->enroll($course, $user, checkPrerequisites: false);
                            if ($result instanceof Enrollment && $result->wasRecentlyCreated) {
                                $user->notify(new TrainingAssigned($result));
                                $assigned++;
                            }
                        }
                        Notification::make()->title($assigned.' kişiye eğitim atandı')->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

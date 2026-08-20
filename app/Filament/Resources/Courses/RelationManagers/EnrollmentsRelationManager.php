<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
                Select::make('user_id')->label('Katılımcı')->relationship('user', 'name')->searchable()->preload()->required(),
                Select::make('course_run_id')->label('Dönem (opsiyonel)')->relationship('run', 'name')->searchable()->preload(),
                Select::make('status')->label('Durum')->options(['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'])->default('enrolled')->required()->native(false),
                DateTimePicker::make('completed_at')->label('Tamamlanma tarihi'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('user.name')->label('Katılımcı')->searchable(),
                TextColumn::make('run.name')->label('Dönem')->placeholder('—'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (string $state) => ['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('completed_at')->label('Tamamlanma')->dateTime('d.m.Y H:i')->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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

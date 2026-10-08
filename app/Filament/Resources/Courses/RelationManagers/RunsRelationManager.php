<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    protected static ?string $title = 'Dönemler';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Dönem adı'))->required()->maxLength(120),
                TextInput::make('code')->label(__('Dönem kodu'))->required()->maxLength(40)->unique(ignoreRecord: true),
                Select::make('status')->label(__('Durum'))->options(['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi'])->default('draft')->required()->native(false),
                DateTimePicker::make('starts_at')->label(__('Başlangıç')),
                DateTimePicker::make('ends_at')->label(__('Bitiş')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Dönem')),
                TextColumn::make('code')->label(__('Kod')),
                TextColumn::make('status')->label(__('Durum'))->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi'][$state] ?? $state),
                TextColumn::make('enrollments_count')->label(__('Kayıt'))->counts('enrollments')->badge(),
                TextColumn::make('starts_at')->label(__('Başlangıç'))->dateTime('d.m.Y'),
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

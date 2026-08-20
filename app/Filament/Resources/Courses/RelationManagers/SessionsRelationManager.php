<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'Seanslar';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label('Seans başlığı')
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('starts_at')->label('Başlangıç'),
                DateTimePicker::make('ends_at')->label('Bitiş'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label('Seans')
                    ->searchable(),
                TextColumn::make('meeting_status')->label('Durum')->badge()->placeholder('Yüz yüze'),
                TextColumn::make('materials_count')->label('Materyal')->counts('materials')->badge(),
                TextColumn::make('starts_at')->label('Başlangıç')->dateTime('d.m.Y H:i'),
                TextColumn::make('ends_at')->label('Bitiş')->dateTime('H:i'),
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

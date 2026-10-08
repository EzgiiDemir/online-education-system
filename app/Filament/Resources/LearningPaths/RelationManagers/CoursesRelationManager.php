<?php

namespace App\Filament\Resources\LearningPaths\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $title = 'Eğitimler (sıralı)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('pivot.sort_order')->label(__('Sıra'))->sortable(),
                TextColumn::make('title')->label(__('Eğitim')),
                IconColumn::make('pivot.is_required')->label(__('Zorunlu'))->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('sort_order')->label(__('Sıra'))->numeric()->default(0),
                        Toggle::make('is_required')->label(__('Zorunlu'))->default(true),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}

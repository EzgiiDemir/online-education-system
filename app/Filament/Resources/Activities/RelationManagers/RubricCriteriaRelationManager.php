<?php

namespace App\Filament\Resources\Activities\RelationManagers;

use App\Models\Activity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * An assignment activity with rows here is graded by rubric instead of a
 * single free-typed 0-100 score -- see LmsController::gradeAssignment().
 * Deliberately no way to author a rubric for a quiz/video/etc. activity;
 * the concept only makes sense for instructor-graded submissions.
 */
class RubricCriteriaRelationManager extends RelationManager
{
    protected static string $relationship = 'rubricCriteria';

    protected static ?string $title = 'Değerlendirme kriterleri (rubrik)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Activity && $ownerRecord->type === 'assignment';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')->label('Kriter')->required()->maxLength(160)->columnSpanFull(),
                TextInput::make('max_points')->label('Maksimum puan')->numeric()->minValue(1)->required(),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')->label('Kriter'),
                TextColumn::make('max_points')->label('Maksimum puan')->badge(),
                TextColumn::make('sort_order')->label('Sıra'),
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

<?php

namespace App\Filament\Resources\CourseRuns;

use App\Filament\Concerns\TranslatesNavigation;
use App\Filament\Resources\CourseRuns\Pages\CreateCourseRun;
use App\Filament\Resources\CourseRuns\Pages\EditCourseRun;
use App\Filament\Resources\CourseRuns\Pages\ListCourseRuns;
use App\Filament\Resources\CourseRuns\Schemas\CourseRunForm;
use App\Filament\Resources\CourseRuns\Tables\CourseRunsTable;
use App\Models\CourseRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CourseRunResource extends Resource
{
    use TranslatesNavigation;

    protected static ?string $model = CourseRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Eğitim dönemleri';

    protected static ?string $modelLabel = 'eğitim dönemi';

    public static function form(Schema $schema): Schema
    {
        return CourseRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseRuns::route('/'),
            'create' => CreateCourseRun::route('/create'),
            'edit' => EditCourseRun::route('/{record}/edit'),
        ];
    }
}

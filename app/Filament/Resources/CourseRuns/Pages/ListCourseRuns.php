<?php

namespace App\Filament\Resources\CourseRuns\Pages;

use App\Filament\Resources\CourseRuns\CourseRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseRuns extends ListRecords
{
    protected static string $resource = CourseRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

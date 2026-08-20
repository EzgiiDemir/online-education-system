<?php

namespace App\Filament\Resources\CourseRuns\Pages;

use App\Filament\Resources\CourseRuns\CourseRunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseRun extends EditRecord
{
    protected static string $resource = CourseRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\CourseSessions\Pages;

use App\Filament\Resources\CourseSessions\CourseSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseSession extends CreateRecord
{
    protected static string $resource = CourseSessionResource::class;
}

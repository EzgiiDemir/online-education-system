<?php

namespace App\Filament\Resources\LearningPaths\Pages;

use App\Filament\Resources\LearningPaths\LearningPathResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLearningPath extends EditRecord
{
    protected static string $resource = LearningPathResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

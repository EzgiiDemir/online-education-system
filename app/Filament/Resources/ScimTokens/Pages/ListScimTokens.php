<?php

namespace App\Filament\Resources\ScimTokens\Pages;

use App\Filament\Resources\ScimTokens\ScimTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScimTokens extends ListRecords
{
    protected static string $resource = ScimTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

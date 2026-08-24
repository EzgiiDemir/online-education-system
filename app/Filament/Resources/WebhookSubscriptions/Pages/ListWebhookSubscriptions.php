<?php

namespace App\Filament\Resources\WebhookSubscriptions\Pages;

use App\Filament\Resources\WebhookSubscriptions\WebhookSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebhookSubscriptions extends ListRecords
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\WebhookSubscriptions\Pages;

use App\Filament\Resources\WebhookSubscriptions\WebhookSubscriptionResource;
use App\Models\WebhookSubscription;
use Filament\Resources\Pages\CreateRecord;

class CreateWebhookSubscription extends CreateRecord
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['secret'] = WebhookSubscription::generateSecret();

        return $data;
    }
}

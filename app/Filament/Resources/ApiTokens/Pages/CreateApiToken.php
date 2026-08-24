<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Same one-time-reveal pattern as CreateScimToken -- only the hash is
 * ever persisted, so this is the only moment the plaintext exists.
 */
class CreateApiToken extends CreateRecord
{
    protected static string $resource = ApiTokenResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$token, $plaintext] = ApiToken::generate($data['name']);

        Notification::make()
            ->title('API token oluşturuldu')
            ->body("Bu token'ı şimdi kopyalayın, bir daha gösterilmeyecek:\n\n{$plaintext}")
            ->success()
            ->persistent()
            ->send();

        return $token;
    }
}

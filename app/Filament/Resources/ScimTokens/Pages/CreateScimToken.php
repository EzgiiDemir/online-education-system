<?php

namespace App\Filament\Resources\ScimTokens\Pages;

use App\Filament\Resources\ScimTokens\ScimTokenResource;
use App\Models\ScimToken;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Only the token's hash is ever persisted (see ScimToken::generate()), so
 * this is the only moment the plaintext value exists anywhere -- shown
 * once here in a persistent notification instead of the record itself,
 * since there's nowhere on the model to safely display it again later.
 */
class CreateScimToken extends CreateRecord
{
    protected static string $resource = ScimTokenResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$token, $plaintext] = ScimToken::generate($data['name']);

        Notification::make()
            ->title('SCIM token oluşturuldu')
            ->body("Bu token'ı şimdi kopyalayın, bir daha gösterilmeyecek:\n\n{$plaintext}")
            ->success()
            ->persistent()
            ->send();

        return $token;
    }
}

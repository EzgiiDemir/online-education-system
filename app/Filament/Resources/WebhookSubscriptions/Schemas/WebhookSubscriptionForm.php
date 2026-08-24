<?php

namespace App\Filament\Resources\WebhookSubscriptions\Schemas;

use App\Models\WebhookSubscription;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WebhookSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Ad')->required()->maxLength(160)
                    ->helperText('Bu aboneliğin hangi sistem/entegrasyon için olduğunu hatırlamanıza yarar.'),
                TextInput::make('url')->label('Hedef URL')->required()->url()->maxLength(500),
                CheckboxList::make('events')->label('Olaylar')->options(WebhookSubscription::EVENTS)->required()->columns(2),
                Toggle::make('is_active')->label('Aktif')->default(true),
                TextInput::make('secret')->label('İmza gizli anahtarı (secret)')
                    ->disabled()->dehydrated()
                    ->helperText('Her teslimatta X-Webhook-Signature başlığı bu anahtarla HMAC-SHA256 olarak imzalanır. Alıcı tarafta doğrulama için kullanın.')
                    ->visibleOn('edit'),
            ]);
    }
}

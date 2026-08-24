<?php

namespace App\Filament\Resources\WebhookSubscriptions;

use App\Filament\Resources\WebhookSubscriptions\Pages\CreateWebhookSubscription;
use App\Filament\Resources\WebhookSubscriptions\Pages\EditWebhookSubscription;
use App\Filament\Resources\WebhookSubscriptions\Pages\ListWebhookSubscriptions;
use App\Filament\Resources\WebhookSubscriptions\RelationManagers\DeliveriesRelationManager;
use App\Filament\Resources\WebhookSubscriptions\Schemas\WebhookSubscriptionForm;
use App\Filament\Resources\WebhookSubscriptions\Tables\WebhookSubscriptionsTable;
use App\Models\WebhookSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebhookSubscriptionResource extends Resource
{
    protected static ?string $model = WebhookSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Webhook abonelikleri';

    public static function form(Schema $schema): Schema
    {
        return WebhookSubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebhookSubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookSubscriptions::route('/'),
            'create' => CreateWebhookSubscription::route('/create'),
            'edit' => EditWebhookSubscription::route('/{record}/edit'),
        ];
    }
}

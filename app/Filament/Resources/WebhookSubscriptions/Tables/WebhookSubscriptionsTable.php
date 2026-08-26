<?php

namespace App\Filament\Resources\WebhookSubscriptions\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookSubscription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebhookSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ad')->searchable(),
                TextColumn::make('url')->label('URL')->limit(40)->searchable(),
                TextColumn::make('events')->label('Olaylar')->badge()
                    ->formatStateUsing(fn (string $state) => WebhookSubscription::EVENTS[$state] ?? $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('deliveries_count')->label('Teslimat')->counts('deliveries')->badge(),
                TextColumn::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('test')
                    ->label('Test gönder')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('gray')
                    ->action(function (WebhookSubscription $record) {
                        DeliverWebhookJob::dispatchSync($record, 'webhook.test', [
                            'message' => 'Bu, Arucad Sürekli Öğrenme Merkezi\'nden gönderilen bir test bildirimdir.',
                        ], isTest: true);
                        Notification::make()->title('Test teslimatı gönderildi, sonucu aşağıdaki geçmişte görebilirsiniz.')->success()->send();
                    }),
                ViewAuditLogAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

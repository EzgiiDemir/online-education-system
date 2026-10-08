<?php

namespace App\Filament\Resources\WebhookSubscriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: a delivery log entry is a historical fact about what was
 * actually sent and what came back, not something anyone should be able
 * to edit after the fact -- same reasoning as AuditLog having no edit UI.
 */
class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Teslimat geçmişi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event')
            ->columns([
                TextColumn::make('event')->label(__('Olay')),
                TextColumn::make('attempt')->label(__('Deneme')),
                TextColumn::make('response_status')->label(__('HTTP durumu'))->placeholder(__('—')),
                IconColumn::make('successful')->label(__('Başarılı'))->boolean(),
                TextColumn::make('created_at')->label(__('Gönderim zamanı'))->dateTime('d.m.Y H:i:s')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}

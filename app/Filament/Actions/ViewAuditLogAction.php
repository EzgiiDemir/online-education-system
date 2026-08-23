<?php

namespace App\Filament\Actions;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * A single reusable "Log" row-action for any Auditable resource table: opens
 * a modal listing that one record's own create/update/delete history from
 * the AuditLog table (written automatically by App\Models\Concerns\Auditable
 * on every mutation). One component instead of hand-building a log view per
 * resource -- attach with ->recordActions([..., ViewAuditLogAction::make()]).
 */
class ViewAuditLogAction
{
    public static function make(): Action
    {
        return Action::make('auditLog')
            ->label('Kayıt geçmişi')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading('Kayıt geçmişi')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Kapat')
            ->modalContent(fn (Model $record) => view('filament.audit-log-modal', [
                'entries' => AuditLog::with('actor')
                    ->where('entity_type', class_basename($record))
                    ->where('entity_id', $record->getKey())
                    ->latest()
                    ->limit(50)
                    ->get(),
            ]));
    }
}

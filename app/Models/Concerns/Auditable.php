<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an immutable AuditLog row on create/update/delete for the models
 * that use this trait. One generic hook instead of hand-instrumenting every
 * mutation point (Filament admin edits, instructor-side course/enrollment
 * changes, everything) -- see Phase 0 audit note on this being the DRY way
 * to get coverage across both the Filament admin panel and the Blade panels.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAuditLog('created', $model->auditableAttributes()));
        static::updated(function ($model) {
            $realChanges = collect($model->getChanges())->except(['updated_at']);
            if ($realChanges->isNotEmpty()) {
                $model->writeAuditLog('updated', $model->auditableDirty());
            }
        });
        static::deleted(fn ($model) => $model->writeAuditLog('deleted', null));
    }

    protected function writeAuditLog(string $action, ?array $changes): void
    {
        AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'entity_type' => class_basename($this),
            'entity_id' => $this->getKey(),
            'entity_label' => $this->auditLabel(),
            'changes' => $changes,
            'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
            'created_at' => now(),
        ]);
    }

    protected function auditLabel(): ?string
    {
        foreach (['title', 'name', 'certificate_number', 'portal_name'] as $field) {
            if (isset($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return null;
    }

    protected function auditableAttributes(): array
    {
        $sensitive = ['/password/i', '/secret/i', '/token/i'];

        return collect($this->getAttributes())
            ->reject(fn ($value, $key) => collect($sensitive)->contains(fn ($p) => preg_match($p, $key)))
            ->toArray();
    }

    protected function auditableDirty(): array
    {
        $sensitive = ['/password/i', '/secret/i', '/token/i'];

        return collect($this->getChanges())
            ->reject(fn ($value, $key) => collect($sensitive)->contains(fn ($p) => preg_match($p, $key)))
            ->reject(fn ($value, $key) => in_array($key, ['updated_at'], true))
            ->map(fn ($value, $key) => ['from' => $this->getOriginal($key), 'to' => $value])
            ->toArray();
    }
}

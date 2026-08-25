<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $extraRoleKeys = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->before(function (DeleteAction $action, User $record) {
                if ($reason = $record->blockingRemovalReason(isDelete: true)) {
                    Notification::make()->danger()->title($reason)->send();
                    $action->halt();
                }
            }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['extra_role_keys'] = $this->record->roles()->pluck('key')
            ->reject(fn (string $key) => $key === $this->record->role)
            ->values()->all();
        $data['is_deactivated'] = $this->record->deactivated_at !== null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->extraRoleKeys = $data['extra_role_keys'] ?? [];
        unset($data['extra_role_keys']);

        $deactivating = ($data['is_deactivated'] ?? false) && $this->record->deactivated_at === null;
        if ($deactivating && ($reason = $this->record->blockingRemovalReason(isDelete: false))) {
            Notification::make()->danger()->title($reason)->send();
            $this->halt();
        }
        $data['deactivated_at'] = ($data['is_deactivated'] ?? false) ? ($this->record->deactivated_at ?? now()) : null;
        unset($data['is_deactivated']);

        return $data;
    }

    protected function afterSave(): void
    {
        $keys = array_unique([$this->record->role, ...$this->extraRoleKeys]);
        $this->record->roles()->sync(Role::whereIn('key', $keys)->pluck('id'));
    }
}

<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $extraRoleKeys = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['extra_role_keys'] = $this->record->roles()->pluck('key')
            ->reject(fn (string $key) => $key === $this->record->role)
            ->values()->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->extraRoleKeys = $data['extra_role_keys'] ?? [];
        unset($data['extra_role_keys']);

        return $data;
    }

    protected function afterSave(): void
    {
        $keys = array_unique([$this->record->role, ...$this->extraRoleKeys]);
        $this->record->roles()->sync(Role::whereIn('key', $keys)->pluck('id'));
    }
}

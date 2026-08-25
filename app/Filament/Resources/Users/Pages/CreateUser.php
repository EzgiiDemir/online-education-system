<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * extra_role_keys isn't a users column -- it drives the role_user pivot
     * (see afterCreate()) and must never reach User::create()'s mass-assign.
     */
    protected array $extraRoleKeys = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->extraRoleKeys = $data['extra_role_keys'] ?? [];
        unset($data['extra_role_keys']);

        $data['deactivated_at'] = ($data['is_deactivated'] ?? false) ? now() : null;
        unset($data['is_deactivated']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $keys = array_unique([$this->record->role, ...$this->extraRoleKeys]);
        $this->record->roles()->sync(Role::whereIn('key', $keys)->pluck('id'));
    }
}

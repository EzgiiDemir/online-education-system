<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kimlik bilgileri')->schema([
                    TextInput::make('name')->label('Ad soyad')->required()->maxLength(255),
                    TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                    Select::make('role')->label('Rol')->options(['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'admin' => 'Sistem Yöneticisi'])->default('learner')->required()->native(false),
                    TextInput::make('password')->label('Şifre')->password()->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Boş bırakılırsa mevcut şifre değişmez.' : null),
                ])->columns(2),
                Section::make('Profil bilgileri')->schema([
                    TextInput::make('department')->label('Departman'),
                    TextInput::make('job_title')->label('Unvan'),
                    Select::make('manager_id')->label('Bağlı olduğu yönetici')->relationship('manager', 'name', fn ($query) => $query->where('role', 'manager'))->searchable()->native(false)->helperText('Bu kişi hangi yöneticinin ekibinde görünsün.'),
                    Select::make('organization_unit_id')->label('Organizasyon birimi')->relationship('organizationUnit', 'name')->searchable()->preload()->native(false),
                    FileUpload::make('avatar_path')->label('Fotoğraf')->image()->disk('public')->directory('avatars')->imageEditor(),
                    Textarea::make('bio')->label('Kısa biyografi')->columnSpanFull(),
                ])->columns(2),
            ]);
    }
}

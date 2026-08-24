<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
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
                    Select::make('role')->label('Birincil rol')->options(['learner' => 'Öğrenci', 'instructor' => 'Eğitmen', 'manager' => 'Yönetici (Manager)', 'admin' => 'Sistem Yöneticisi'])->default('learner')->required()->native(false)
                        ->helperText('Giriş yaptığında varsayılan olarak yönlendirileceği çalışma alanı.'),
                    CheckboxList::make('extra_role_keys')->label('Ek roller')
                        ->options(fn () => Role::orderBy('name')->pluck('name', 'key'))
                        ->columns(2)
                        ->helperText('Aynı kişi birden fazla çalışma alanına ihtiyaç duyuyorsa (ör. hem Eğitmen hem Yönetici) buradan ek rol verin -- birincil rolü tekrar seçmenize gerek yok, otomatik dahil edilir. Kullanıcı girişte üstteki çalışma alanı değiştiriciden diğerlerine geçebilir.'),
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

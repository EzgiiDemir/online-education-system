<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Kimlik bilgileri'))->schema([
                    TextInput::make('name')->label(__('Ad soyad'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('E-posta'))->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                    Select::make('role')->label(__('Birincil rol'))->options(['learner' => __('Öğrenci'), 'instructor' => __('Eğitmen'), 'manager' => __('Yönetici (Manager)'), 'admin' => __('Sistem Yöneticisi')])->default('learner')->required()->native(false)
                        ->helperText(__('Giriş yaptığında varsayılan olarak yönlendirileceği çalışma alanı.')),
                    CheckboxList::make('extra_role_keys')->label(__('Ek roller'))
                        ->options(fn () => Role::orderBy('name')->get()->mapWithKeys(fn ($role) => [$role->key => __($role->name)]))
                        ->columns(2)
                        ->helperText(__('Aynı kişi birden fazla çalışma alanına ihtiyaç duyuyorsa (ör. hem Eğitmen hem Yönetici) buradan ek rol verin -- birincil rolü tekrar seçmenize gerek yok, otomatik dahil edilir. Kullanıcı girişte üstteki çalışma alanı değiştiriciden diğerlerine geçebilir.')),
                    TextInput::make('password')->label(__('Şifre'))->password()->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? __('Boş bırakılırsa mevcut şifre değişmez.') : null),
                ])->columns(2),
                Section::make(__('Profil bilgileri'))->schema([
                    TextInput::make('department')->label(__('Departman')),
                    TextInput::make('job_title')->label(__('Unvan')),
                    Select::make('manager_id')->label(__('Bağlı olduğu yönetici'))->relationship('manager', 'name', fn ($query) => $query->where('role', 'manager'))->searchable()->native(false)->helperText(__('Bu kişi hangi yöneticinin ekibinde görünsün.')),
                    Select::make('organization_unit_id')->label(__('Organizasyon birimi'))->relationship('organizationUnit', 'name')->searchable()->preload()->native(false),
                    FileUpload::make('avatar_path')->label(__('Fotoğraf'))->image()->disk('public')->directory('avatars')->imageEditor(),
                    Textarea::make('bio')->label(__('Kısa biyografi'))->columnSpanFull(),
                    Toggle::make('is_deactivated')->label(__('Hesap devre dışı'))->columnSpanFull()
                        ->helperText(__('Devre dışı bırakılan kullanıcı giriş yapamaz, ama tüm kayıt/sertifika geçmişi korunur -- silmenin yerine tercih edin.')),
                ])->columns(2),
            ]);
    }
}

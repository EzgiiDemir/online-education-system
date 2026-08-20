<?php

namespace App\Filament\Resources\PortalSettings\Schemas;
use Filament\Forms\Components\TextInput; use Filament\Forms\Components\Toggle; use Filament\Forms\Components\TagsInput; use Filament\Forms\Components\FileUpload; use Filament\Schemas\Components\Section; use Filament\Forms\Components\Placeholder; use Filament\Forms\Components\Select;

use Filament\Schemas\Schema;

class PortalSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Portal kimliği')->schema([TextInput::make('portal_name')->label('Portal adı')->required(),Select::make('default_locale')->label('Varsayılan dil')->options(['tr'=>'Türkçe','en'=>'English'])->default('tr'),TagsInput::make('available_locales')->label('Aktif diller')->default(['tr','en']),FileUpload::make('logo_path')->label('Logo')->image()->disk('public')->directory('branding')])->columns(2),
                Section::make('Giriş ve domain politikası')->schema([TagsInput::make('accepted_domains')->label('Kabul edilen domainler')->placeholder('company.com'),Toggle::make('local_login_enabled')->label('Yerel e-posta/şifre girişi'),Toggle::make('self_registration_enabled')->label('Kullanıcı kendi kaydını oluşturabilir')])->columns(2),
                Section::make('Microsoft Entra ID')->description('Client secret şifreli olarak saklanır.')->schema([Toggle::make('entra_enabled')->label('Entra girişini etkinleştir'),TextInput::make('entra_tenant_id')->label('Tenant ID'),TextInput::make('entra_client_id')->label('Client ID'),TextInput::make('entra_client_secret')->label('Client secret')->password()->revealable(),TextInput::make('entra_redirect_uri')->label('Redirect URI')->url()])->columns(2),
                Section::make('Canlı eğitim')->schema([Toggle::make('internal_class_enabled')->label('Portal içi sınıfı etkinleştir')->default(true),Placeholder::make('class_note')->label('Not')->content('Canlı dersler portal içi oda ekranında açılır; dış toplantı bağlantısı zorunlu değildir.')]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\PortalSettings\Schemas;

use App\Models\PortalSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortalSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Portal kimliği'))->schema([TextInput::make('portal_name')->label(__('Portal adı'))->required(), Select::make('default_locale')->label(__('Varsayılan dil'))->options(['tr' => 'Türkçe', 'en' => 'English'])->default('tr'), TagsInput::make('available_locales')->label(__('Aktif diller'))->default(['tr', 'en']), FileUpload::make('logo_path')->label(__('Logo'))->image()->disk('public')->directory('branding')->visibility('public')])->columns(2),
                Section::make(__('Giriş ve domain politikası'))->schema([TagsInput::make('accepted_domains')->label(__('Kabul edilen domainler'))->placeholder(__('company.com')), Toggle::make('local_login_enabled')->label(__('Yerel e-posta/şifre girişi')), Toggle::make('self_registration_enabled')->label(__('Kullanıcı kendi kaydını oluşturabilir'))])->columns(2),
                Section::make(__('Microsoft Entra ID'))->description(__('Client secret şifreli olarak saklanır.'))->schema([Toggle::make('entra_enabled')->label(__('Entra girişini etkinleştir')), TextInput::make('entra_tenant_id')->label(__('Tenant ID')), TextInput::make('entra_client_id')->label(__('Client ID')), TextInput::make('entra_client_secret')->label(__('Client secret'))->password()->revealable(), TextInput::make('entra_redirect_uri')->label(__('Redirect URI'))->url()])->columns(2),
                Section::make(__('Canlı eğitim'))->schema([Toggle::make('internal_class_enabled')->label(__('Portal içi sınıfı etkinleştir'))->default(true), Placeholder::make('class_note')->label(__('Not'))->content('Canlı dersler portal içi oda ekranında açılır; dış toplantı bağlantısı zorunlu değildir.')]),
                // Printed on every certificate PDF. Editable here so a change
                // of rector is an admin edit, not a code change + redeploy.
                Section::make(__('Sertifika imza bloğu'))->description(__('Sertifika PDF’inde görünen kurum ve imza bilgileri. Boş bırakılırsa resmî ARUCAD katılım belgesindeki varsayılanlar kullanılır.'))->schema([
                    TextInput::make('certificate_issuer_name')->label(__('Kurum adı'))->placeholder(PortalSetting::DEFAULT_ISSUER_NAME)->maxLength(255)->helperText(__('“… ev sahipliğinde gerçekleşen” cümlesinde geçer.')),
                    TextInput::make('certificate_signatory_name')->label(__('İmza sahibi'))->placeholder(PortalSetting::DEFAULT_SIGNATORY_NAME)->maxLength(255),
                    TextInput::make('certificate_signatory_title')->label(__('İmza sahibinin unvanı'))->placeholder(PortalSetting::DEFAULT_SIGNATORY_TITLE)->maxLength(255),
                ])->columns(2),
            ]);
    }
}

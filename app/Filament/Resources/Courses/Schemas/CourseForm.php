<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Temel bilgiler'))->schema([
                    TextInput::make('title')->label(__('Eğitim adı'))->required()->maxLength(255),
                    Select::make('instructor_id')->label(__('Eğitmen'))->relationship('instructor', 'name')->searchable()->preload()->required(),
                    Textarea::make('description')->label(__('Eğitim açıklaması'))->rows(4)->columnSpanFull(),
                    Select::make('delivery_type')->label(__('Eğitim türü'))->options(['online' => 'Online', 'offline' => 'Offline'])->required(),
                    ToggleButtons::make('language')->label(__('Eğitim dili'))->options(['tr' => '🇹🇷 Türkçe', 'en' => '🇬🇧 English'])->inline()->live()->default('tr')->required(),
                    Select::make('status')->label(__('Yayın durumu'))->options(['draft' => __('Taslak'), 'published' => __('Yayınlandı'), 'completed' => __('Tamamlandı')])->default('draft')->required(),
                    DateTimePicker::make('starts_at')->label(__('Başlangıç')), DateTimePicker::make('ends_at')->label(__('Bitiş')),
                ])->columns(2),
                Section::make(__('Sınıflandırma ve uyumluluk'))->schema([
                    Select::make('category')->label(__('Eğitim kategorisi'))->options(['required' => __('Zorunlu'), 'optional' => __('İsteğe bağlı'), 'compliance' => __('Uyumluluk (Compliance)'), 'onboarding' => __('Oryantasyon'), 'professional_development' => __('Mesleki gelişim')])->default('optional')->required(),
                    TextInput::make('due_days')->label(__('Tamamlama süresi (gün)'))->numeric()->minValue(1)->helperText(__('Kayıttan itibaren kaç gün içinde tamamlanmalı. Boş bırakılırsa süre sınırı yok.')),
                    TextInput::make('recertification_period_days')->label(__('Yeniden sertifikalandırma süresi (gün)'))->numeric()->minValue(1)->helperText(__('Sertifika verildikten kaç gün sonra geçerliliğini yitirir. Uyumluluk eğitimleri için kullanılır, boş bırakılırsa süresiz geçerlidir.')),
                    Select::make('skills')->label(__('Kazandırdığı yetkinlikler'))->relationship('skills', 'name')->multiple()->searchable()->preload()->createOptionForm([
                        TextInput::make('name')->label(__('Yetkinlik adı'))->required()->unique(),
                    ]),
                    Select::make('prerequisites')->label(__('Ön koşul eğitimler'))->columnSpanFull()
                        ->relationship('prerequisites', 'title', modifyQueryUsing: fn ($query, $record) => $record ? $query->whereKeyNot($record->id) : $query)
                        ->multiple()->searchable()->preload()
                        ->helperText(__('Bu eğitime kayıt olmadan önce öğrencinin tamamlamış olması gereken eğitimler.')),
                ])->columns(2),
                Section::make(__('Misafir eğitimci'))->description(__('Kurum dışından katkı sunan eğitmen'))->schema([
                    TextInput::make('guest_instructor_name')->label(__('Ad soyad')), TextInput::make('guest_instructor_email')->label(__('E-posta'))->email(), Textarea::make('guest_instructor_bio')->label(__('Kısa biyografi'))->columnSpanFull(),
                ])->columns(2),
                Section::make(__('Sertifika ve SDG'))->schema([
                    Toggle::make('certificate_enabled')->label(__('Tamamlanınca sertifika üret'))->default(true),
                    ViewField::make('sdg_goals')->label(__('Sürdürülebilir Kalkınma Amaçları (SKA / SDG)'))->view('filament.forms.sdg-picker')->viewData(['goals' => [
                        'tr' => ['1' => 'Yoksulluğa son', '2' => 'Açlığa son', '3' => 'Sağlık ve kaliteli yaşam', '4' => 'Nitelikli eğitim', '5' => 'Toplumsal cinsiyet eşitliği', '6' => 'Temiz su ve sanitasyon', '7' => 'Erişilebilir ve temiz enerji', '8' => 'İnsana yakışır iş ve ekonomik büyüme', '9' => 'Sanayi, yenilikçilik ve altyapı', '10' => 'Eşitsizliklerin azaltılması', '11' => 'Sürdürülebilir şehirler ve topluluklar', '12' => 'Sorumlu üretim ve tüketim', '13' => 'İklim eylemi', '14' => 'Sudaki yaşam', '15' => 'Karasal yaşam', '16' => 'Barış, adalet ve güçlü kurumlar', '17' => 'Amaçlar için ortaklıklar'],
                        'en' => ['1' => 'No poverty', '2' => 'Zero hunger', '3' => 'Good health and well-being', '4' => 'Quality education', '5' => 'Gender equality', '6' => 'Clean water and sanitation', '7' => 'Affordable and clean energy', '8' => 'Decent work and economic growth', '9' => 'Industry, innovation and infrastructure', '10' => 'Reduced inequalities', '11' => 'Sustainable cities and communities', '12' => 'Responsible consumption and production', '13' => 'Climate action', '14' => 'Life below water', '15' => 'Life on land', '16' => 'Peace, justice and strong institutions', '17' => 'Partnerships for the goals'],
                    ]])->columnSpanFull(),
                ])->columns(2),
                Section::make(__('Görseller'))->schema([
                    FileUpload::make('poster_path')->label(__('Afiş'))->image()->disk('public')->directory('course-posters')->imageEditor(),
                    FileUpload::make('image_path')->label(__('Kapak / eğitim görseli'))->image()->disk('public')->directory('course-images')->imageEditor(),
                ])->columns(2),
            ]);
    }
}

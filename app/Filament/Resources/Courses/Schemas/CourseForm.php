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
                Section::make('Temel bilgiler')->schema([
                    TextInput::make('title')->label('Eğitim adı')->required()->maxLength(255),
                    Select::make('instructor_id')->label('Eğitmen')->relationship('instructor', 'name')->searchable()->preload()->required(),
                    Textarea::make('description')->label('Eğitim açıklaması')->rows(4)->columnSpanFull(),
                    Select::make('delivery_type')->label('Eğitim türü')->options(['online' => 'Online', 'offline' => 'Offline'])->required(),
                    ToggleButtons::make('language')->label('Eğitim dili')->options(['tr' => '🇹🇷 Türkçe', 'en' => '🇬🇧 English'])->inline()->live()->default('tr')->required(),
                    Select::make('status')->label('Yayın durumu')->options(['draft' => 'Taslak', 'published' => 'Yayınlandı', 'completed' => 'Tamamlandı'])->default('draft')->required(),
                    DateTimePicker::make('starts_at')->label('Başlangıç'), DateTimePicker::make('ends_at')->label('Bitiş'),
                ])->columns(2),
                Section::make('Misafir eğitimci')->description('Kurum dışından katkı sunan eğitmen')->schema([
                    TextInput::make('guest_instructor_name')->label('Ad soyad'), TextInput::make('guest_instructor_email')->label('E-posta')->email(), Textarea::make('guest_instructor_bio')->label('Kısa biyografi')->columnSpanFull(),
                ])->columns(2),
                Section::make('Sertifika ve SDG')->schema([
                    Toggle::make('certificate_enabled')->label('Tamamlanınca sertifika üret')->default(true),
                    ViewField::make('sdg_goals')->label('Sürdürülebilir Kalkınma Amaçları (SKA / SDG)')->view('filament.forms.sdg-picker')->viewData(['goals' => [
                        'tr' => ['1' => 'Yoksulluğa son', '2' => 'Açlığa son', '3' => 'Sağlık ve kaliteli yaşam', '4' => 'Nitelikli eğitim', '5' => 'Toplumsal cinsiyet eşitliği', '6' => 'Temiz su ve sanitasyon', '7' => 'Erişilebilir ve temiz enerji', '8' => 'İnsana yakışır iş ve ekonomik büyüme', '9' => 'Sanayi, yenilikçilik ve altyapı', '10' => 'Eşitsizliklerin azaltılması', '11' => 'Sürdürülebilir şehirler ve topluluklar', '12' => 'Sorumlu üretim ve tüketim', '13' => 'İklim eylemi', '14' => 'Sudaki yaşam', '15' => 'Karasal yaşam', '16' => 'Barış, adalet ve güçlü kurumlar', '17' => 'Amaçlar için ortaklıklar'],
                        'en' => ['1' => 'No poverty', '2' => 'Zero hunger', '3' => 'Good health and well-being', '4' => 'Quality education', '5' => 'Gender equality', '6' => 'Clean water and sanitation', '7' => 'Affordable and clean energy', '8' => 'Decent work and economic growth', '9' => 'Industry, innovation and infrastructure', '10' => 'Reduced inequalities', '11' => 'Sustainable cities and communities', '12' => 'Responsible consumption and production', '13' => 'Climate action', '14' => 'Life below water', '15' => 'Life on land', '16' => 'Peace, justice and strong institutions', '17' => 'Partnerships for the goals'],
                    ]])->columnSpanFull(),
                ])->columns(2),
                Section::make('Görseller')->schema([
                    FileUpload::make('poster_path')->label('Afiş')->image()->disk('public')->directory('course-posters')->imageEditor(),
                    FileUpload::make('image_path')->label('Kapak / eğitim görseli')->image()->disk('public')->directory('course-images')->imageEditor(),
                ])->columns(2),
            ]);
    }
}

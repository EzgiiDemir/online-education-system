<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_section_id')->label('Bölüm')->relationship('section', 'title')->searchable()->preload()->required(),
                TextInput::make('title')->label('Aktivite adı')->required()->maxLength(160),
                Select::make('type')->label('Aktivite türü')->options(['video' => 'Video', 'document' => 'Doküman / PDF', 'presentation' => 'Sunum', 'live_session' => 'Canlı oturum', 'resource' => 'Kaynak', 'quiz' => 'Sınav (Quiz)', 'assignment' => 'Ödev'])->required()->live(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('path')->label('Dosya veya kaynak yolu')->placeholder('storage/materials/...')->visible(fn (Get $get) => ! in_array($get('type'), ['quiz', 'assignment'], true)),
                TextInput::make('duration_seconds')->label('Süre (saniye)')->numeric()->minValue(0)->visible(fn (Get $get) => ! in_array($get('type'), ['quiz', 'assignment'], true)),
                TextInput::make('pass_score')->label('Geçme notu (%)')->numeric()->minValue(0)->maxValue(100)->visible(fn (Get $get) => in_array($get('type'), ['quiz', 'assignment'], true)),
                TextInput::make('max_attempts')->label('Maksimum deneme hakkı')->numeric()->minValue(1)->helperText('Boş = sınırsız')->visible(fn (Get $get) => $get('type') === 'quiz'),
                TextInput::make('pool_size')->label('Denemede sorulacak soru sayısı')->numeric()->minValue(1)
                    ->helperText('Soru bankasından her denemede rastgele kaç soru sorulacağı. Boş = bankadaki tüm sorular.')
                    ->visible(fn (Get $get) => $get('type') === 'quiz'),
                Select::make('submission_type')->label('Teslim türü')->options(['text' => 'Metin', 'file' => 'Dosya', 'url' => 'Bağlantı (URL)'])->default('text')->visible(fn (Get $get) => $get('type') === 'assignment'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_required')->label('Tamamlanması zorunlu')->default(true),
            ]);
    }
}

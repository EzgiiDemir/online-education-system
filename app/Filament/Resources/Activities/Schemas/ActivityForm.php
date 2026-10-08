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
                Select::make('course_section_id')->label(__('Bölüm'))->relationship('section', 'title')->searchable()->preload()->required(),
                TextInput::make('title')->label(__('Aktivite adı'))->required()->maxLength(160),
                Select::make('type')->label(__('Aktivite türü'))->options(['video' => __('Video'), 'document' => __('Doküman / PDF'), 'presentation' => __('Sunum'), 'live_session' => __('Canlı oturum'), 'resource' => __('Kaynak'), 'quiz' => __('Sınav (Quiz)'), 'assignment' => __('Ödev')])->required()->live(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('path')->label(__('Dosya veya kaynak yolu'))->placeholder(__('storage/materials/...'))->visible(fn (Get $get) => ! in_array($get('type'), ['quiz', 'assignment'], true)),
                TextInput::make('duration_seconds')->label(__('Süre (saniye)'))->numeric()->minValue(0)->visible(fn (Get $get) => ! in_array($get('type'), ['quiz', 'assignment'], true)),
                TextInput::make('pass_score')->label(__('Geçme notu (%)'))->numeric()->minValue(0)->maxValue(100)->visible(fn (Get $get) => in_array($get('type'), ['quiz', 'assignment'], true)),
                TextInput::make('max_attempts')->label(__('Maksimum deneme hakkı'))->numeric()->minValue(1)->helperText(__('Boş = sınırsız'))->visible(fn (Get $get) => $get('type') === 'quiz'),
                TextInput::make('pool_size')->label(__('Denemede sorulacak soru sayısı'))->numeric()->minValue(1)
                    ->helperText(__('Soru bankasından her denemede rastgele kaç soru sorulacağı. Boş = bankadaki tüm sorular.'))
                    ->visible(fn (Get $get) => $get('type') === 'quiz'),
                Select::make('submission_type')->label(__('Teslim türü'))->options(['text' => __('Metin'), 'file' => __('Dosya'), 'url' => __('Bağlantı (URL)')])->default('text')->visible(fn (Get $get) => $get('type') === 'assignment'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_required')->label(__('Tamamlanması zorunlu'))->default(true),
            ]);
    }
}

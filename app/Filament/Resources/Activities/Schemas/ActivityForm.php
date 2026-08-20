<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_section_id')->label('Bölüm')->relationship('section', 'title')->searchable()->preload()->required(),
                TextInput::make('title')->label('Aktivite adı')->required()->maxLength(160),
                Select::make('type')->label('Aktivite türü')->options(['video'=>'Video','document'=>'Doküman / PDF','presentation'=>'Sunum','live_session'=>'Canlı oturum','resource'=>'Kaynak'])->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('path')->label('Dosya veya kaynak yolu')->placeholder('storage/materials/...'),
                TextInput::make('duration_seconds')->label('Süre (saniye)')->numeric()->minValue(0),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_required')->label('Tamamlanması zorunlu')->default(true),
            ]);
    }
}

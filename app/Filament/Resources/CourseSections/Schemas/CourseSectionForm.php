<?php

namespace App\Filament\Resources\CourseSections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload()->required(),
                TextInput::make('title')->label('Bölüm başlığı')
                    ->required(),
                Textarea::make('description')->label('Açıklama')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('Sıralama')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

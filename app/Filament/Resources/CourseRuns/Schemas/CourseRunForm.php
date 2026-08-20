<?php

namespace App\Filament\Resources\CourseRuns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload()->required(),
                TextInput::make('name')->label('Dönem adı')->required()->maxLength(120),
                TextInput::make('code')->label('Dönem kodu')->required()->maxLength(40),
                Select::make('language')->label('Eğitim dili')->options(['tr' => 'Türkçe', 'en' => 'English'])->required()->default('tr'),
                DateTimePicker::make('enrollment_opens_at'),
                DateTimePicker::make('enrollment_closes_at'),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                Select::make('status')->label('Durum')->options(['draft' => 'Taslak', 'published' => 'Yayında', 'closed' => 'Kapalı'])->required()->default('draft'),
            ]);
    }
}

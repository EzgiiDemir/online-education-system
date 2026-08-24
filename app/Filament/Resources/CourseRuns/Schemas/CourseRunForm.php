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
                TextInput::make('code')->label('Dönem kodu')->required()->maxLength(40)->unique(ignoreRecord: true),
                Select::make('language')->label('Eğitim dili')->options(['tr' => 'Türkçe', 'en' => 'English'])->required()->default('tr'),
                DateTimePicker::make('enrollment_opens_at')->label('Kayıt başlangıcı'),
                DateTimePicker::make('enrollment_closes_at')->label('Kayıt bitişi'),
                DateTimePicker::make('starts_at')->label('Dönem başlangıcı'),
                DateTimePicker::make('ends_at')->label('Dönem bitişi'),
                Select::make('status')->label('Durum')->options(['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi'])->required()->default('draft')->helperText('Yalnızca "Kayıt açık" veya "Devam ediyor" durumundaki dönemler, öğrenci kayıt olduğunda otomatik atanır.'),
                TextInput::make('max_seats')->label('Maksimum kontenjan')->numeric()->minValue(1)->helperText('Boş = sınırsız. Kontenjan dolunca yeni kayıtlar bekleme listesine alınır.'),
            ]);
    }
}

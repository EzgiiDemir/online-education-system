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
                Select::make('course_id')->label(__('Eğitim'))->relationship('course', 'title')->searchable()->preload()->required(),
                TextInput::make('name')->label(__('Dönem adı'))->required()->maxLength(120),
                TextInput::make('code')->label(__('Dönem kodu'))->required()->maxLength(40)->unique(ignoreRecord: true),
                Select::make('language')->label(__('Eğitim dili'))->options(['tr' => 'Türkçe', 'en' => 'English'])->required()->default('tr'),
                DateTimePicker::make('enrollment_opens_at')->label(__('Kayıt başlangıcı')),
                DateTimePicker::make('enrollment_closes_at')->label(__('Kayıt bitişi')),
                DateTimePicker::make('starts_at')->label(__('Dönem başlangıcı')),
                DateTimePicker::make('ends_at')->label(__('Dönem bitişi')),
                Select::make('status')->label(__('Durum'))->options(['draft' => 'Taslak', 'open' => 'Kayıt açık', 'running' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'archived' => 'Arşivlendi'])->required()->default('draft')->helperText(__('Yalnızca "Kayıt açık" veya "Devam ediyor" durumundaki dönemler, öğrenci kayıt olduğunda otomatik atanır.')),
                TextInput::make('max_seats')->label(__('Maksimum kontenjan'))->numeric()->minValue(1)->helperText(__('Boş = sınırsız. Kontenjan dolunca yeni kayıtlar bekleme listesine alınır.')),
            ]);
    }
}

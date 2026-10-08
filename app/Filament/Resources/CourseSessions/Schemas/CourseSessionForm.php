<?php

namespace App\Filament\Resources\CourseSessions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')->label(__('Eğitim'))->relationship('course', 'title')->searchable()->preload()->required(),
                TextInput::make('title')->label(__('Seans başlığı'))
                    ->required(),
                DateTimePicker::make('starts_at')->label(__('Başlangıç')),
                DateTimePicker::make('ends_at')->label(__('Bitiş')),
                TextInput::make('room_key')->label(__('Otomatik toplantı oda anahtarı'))->disabled()->dehydrated(false)->helperText(__('Online seans oluşturulunca sistem tarafından üretilir.')),
                TextInput::make('meeting_status')->label(__('Toplantı durumu'))->disabled()->dehydrated(false),
                TextInput::make('recording_path')->label(__('Kayıt dosyası'))->disabled()->dehydrated(false)->helperText(__('Eğitmenin canlı sınıf kaydı yüklemesiyle otomatik doldurulur.')),
            ]);
    }
}

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
                Select::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload()->required(),
                TextInput::make('title')->label('Seans başlığı')
                    ->required(),
                DateTimePicker::make('starts_at')->label('Başlangıç'),
                DateTimePicker::make('ends_at')->label('Bitiş'),
                TextInput::make('room_key')->label('Otomatik toplantı oda anahtarı')->disabled()->dehydrated(false)->helperText('Online seans oluşturulunca sistem tarafından üretilir.'),
                TextInput::make('meeting_status')->label('Toplantı durumu')->disabled()->dehydrated(false),
                TextInput::make('recording_path')->label('Kayıt dosyası')->disabled()->dehydrated(false)->helperText('Eğitmenin canlı sınıf kaydı yüklemesiyle otomatik doldurulur.'),
            ]);
    }
}

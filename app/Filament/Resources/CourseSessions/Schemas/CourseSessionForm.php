<?php

namespace App\Filament\Resources\CourseSessions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('course_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                TextInput::make('room_key')->label('Otomatik toplantı oda anahtarı')->disabled()->dehydrated(false)->helperText('Online seans oluşturulunca sistem tarafından üretilir.'),
                TextInput::make('meeting_status')->label('Toplantı durumu')->disabled()->dehydrated(false),
                TextInput::make('recording_path'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->label('Katılımcı')->relationship('user', 'name')->searchable()->preload()->required(),
                Select::make('course_id')->label('Eğitim')->relationship('course', 'title')->searchable()->preload()->required()->live(),
                Select::make('course_run_id')->label('Dönem (opsiyonel)')->relationship('run', 'name')->searchable()->preload(),
                Select::make('status')->label('Durum')->options(['enrolled' => 'Kayıtlı', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı'])->default('enrolled')->required()->native(false),
                DateTimePicker::make('completed_at')->label('Tamamlanma tarihi'),
            ]);
    }
}

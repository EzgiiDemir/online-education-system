<?php

namespace App\Filament\Resources\Materials\Schemas;

use App\Models\CourseSession;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_session_id')->label('Seans')->required()->searchable()->preload()
                    ->options(fn () => CourseSession::with('course')->get()->mapWithKeys(fn ($session) => [$session->id => $session->course->title.' — '.$session->title]))
                    ->getSearchResultsUsing(fn (string $search) => CourseSession::with('course')->where('title', 'like', "%{$search}%")->limit(50)->get()->mapWithKeys(fn ($session) => [$session->id => $session->course->title.' — '.$session->title])),
                TextInput::make('title')->label('Materyal adı')->required()->maxLength(255),
                TextInput::make('type')->label('Dosya türü')->required()->placeholder('örn. video/mp4, application/pdf')->helperText('Dosyayı yüklerseniz otomatik doldurulur.'),
                FileUpload::make('path')->label('Dosya')->disk('public')->directory('materials')->required()
                    ->afterStateUpdated(fn ($state, callable $set) => $state && $set('type', $state->getMimeType()))
                    ->live(),
                TextInput::make('duration_seconds')->label('Süre (saniye)')->numeric()->minValue(0)->helperText('Belirtilirse, öğrenci tamamlamak için bu sürenin en az %90\'ını izlemiş olmalıdır.'),
            ]);
    }
}

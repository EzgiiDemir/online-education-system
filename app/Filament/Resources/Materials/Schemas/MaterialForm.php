<?php

namespace App\Filament\Resources\Materials\Schemas;

use App\Models\CourseSession;
use App\Support\ProtectedFiles;
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
                Select::make('course_session_id')->label(__('Seans'))->required()->searchable()->preload()
                    ->options(fn () => CourseSession::with('course')->get()->mapWithKeys(fn ($session) => [$session->id => $session->course->title.' — '.$session->title]))
                    ->getSearchResultsUsing(fn (string $search) => CourseSession::with('course')->whereLike('title', "%{$search}%", caseSensitive: false)->limit(50)->get()->mapWithKeys(fn ($session) => [$session->id => $session->course->title.' — '.$session->title])),
                TextInput::make('title')->label(__('Materyal adı'))->required()->maxLength(255),
                TextInput::make('type')->label(__('Dosya türü'))->required()->placeholder(__('örn. video/mp4, application/pdf'))->helperText(__('Dosyayı yüklerseniz otomatik doldurulur.')),
                FileUpload::make('path')->label(__('Dosya'))->disk(ProtectedFiles::DISK)->visibility('private')->directory('materials')->required()
                    ->maxSize(512000)->acceptedFileTypes(ProtectedFiles::ACCEPTED_MIME_TYPES)
                    ->afterStateUpdated(fn ($state, callable $set) => $state && $set('type', $state->getMimeType()))
                    ->live(),
                TextInput::make('duration_seconds')->label(__('Süre (saniye)'))->numeric()->minValue(0)->helperText(__('Belirtilirse, öğrenci tamamlamak için bu sürenin en az %90\'ını izlemiş olmalıdır.')),
            ]);
    }
}

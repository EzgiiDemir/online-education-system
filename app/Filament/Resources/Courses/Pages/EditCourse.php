<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createVersion')
                ->label(__('Yeni sürüm oluştur'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Bu eğitimin (müfredatıyla birlikte) taslak durumda yeni bir sürümü oluşturulur. Mevcut kayıtlar ve sertifikalar bu eğitimin şu anki sürümüne bağlı kalmaya devam eder, hiçbir şey değişmez.')
                ->action(function () {
                    $newVersion = $this->record->createNewVersion();
                    Notification::make()->title(__('v').$newVersion->version.' oluşturuldu')->success()->send();

                    return redirect(CourseResource::getUrl('edit', ['record' => $newVersion]));
                }),
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\LearningPaths\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LearningPathForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label(__('Yol adı'))->required()->maxLength(255),
                Textarea::make('description')->label(__('Açıklama'))->columnSpanFull(),
                Toggle::make('is_sequential')->label(__('Sıralı ilerleme'))->default(true)->helperText(__('Açıksa, zorunlu bir eğitim öncekini tamamlamadan kilitli görünür.')),
                Select::make('status')->label(__('Durum'))->options(['draft' => __('Taslak'), 'published' => __('Yayında')])->default('draft')->required(),
            ]);
    }
}

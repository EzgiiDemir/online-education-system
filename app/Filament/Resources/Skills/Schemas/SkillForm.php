<?php

namespace App\Filament\Resources\Skills\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Yetkinlik adı')->required()->unique(ignoreRecord: true)->maxLength(255),
                Select::make('courses')->label('Bu yetkinliği kazandıran eğitimler')->relationship('courses', 'title')->multiple()->searchable()->preload(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\ApiTokens\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApiTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Token adı')->required()->maxLength(160)
                    ->helperText('Bu token\'ı hangi raporlama/entegrasyon aracının kullandığını hatırlamanıza yarar.'),
            ]);
    }
}

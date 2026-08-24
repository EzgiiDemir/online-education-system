<?php

namespace App\Filament\Resources\ScimTokens\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ScimTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Token adı')->required()->maxLength(160)
                    ->helperText('Bu token\'ı hangi kimlik sağlayıcının (Okta, Entra ID, vb.) kullandığını hatırlamanıza yarar.'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\ScimTokens;

use App\Filament\Resources\ScimTokens\Pages\CreateScimToken;
use App\Filament\Resources\ScimTokens\Pages\ListScimTokens;
use App\Filament\Resources\ScimTokens\Schemas\ScimTokenForm;
use App\Filament\Resources\ScimTokens\Tables\ScimTokensTable;
use App\Models\ScimToken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\TranslatesNavigation;

/**
 * No edit page: a token's only meaningful attributes are its name (create)
 * and its hash (never editable, never re-displayed). Rotating a credential
 * means revoking (delete) and issuing a new one, not editing an old one.
 */
class ScimTokenResource extends Resource
{
    use TranslatesNavigation;

    protected static ?string $model = ScimToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'SCIM token\'ları';

    protected static ?string $modelLabel = 'SCIM token';

    public static function form(Schema $schema): Schema
    {
        return ScimTokenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScimTokensTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScimTokens::route('/'),
            'create' => CreateScimToken::route('/create'),
        ];
    }
}

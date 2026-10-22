<?php

namespace App\Filament\Resources\ApiTokens;

use App\Filament\Resources\ApiTokens\Pages\CreateApiToken;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Filament\Resources\ApiTokens\Schemas\ApiTokenForm;
use App\Filament\Resources\ApiTokens\Tables\ApiTokensTable;
use App\Models\ApiToken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Concerns\TranslatesNavigation;

class ApiTokenResource extends Resource
{
    use TranslatesNavigation;

    protected static ?string $model = ApiToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'API token\'ları';

    protected static ?string $modelLabel = 'API token';

    public static function form(Schema $schema): Schema
    {
        return ApiTokenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiTokensTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiTokens::route('/'),
            'create' => CreateApiToken::route('/create'),
        ];
    }
}

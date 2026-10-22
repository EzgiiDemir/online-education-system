<?php

namespace App\Filament\Resources\Certificates;

use App\Filament\Concerns\TranslatesNavigation;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Resources\Certificates\Tables\CertificatesTable;
use App\Models\Certificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * No create/edit forms on purpose: a certificate is only ever meant to be
 * produced by CompletionEngine::evaluateEnrollment() when a learner
 * genuinely finishes a course, never hand-typed by an admin. The one
 * mutation this resource allows is revoke (see CertificatesTable) --
 * certificates are audit-adjacent records, so "delete" was never on the
 * table here (see the Auditable trait already on this model).
 */
class CertificateResource extends Resource
{
    use TranslatesNavigation;

    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Katılımcılar';

    protected static ?string $navigationLabel = 'Sertifikalar';

    protected static ?string $modelLabel = 'sertifika';

    public static function table(Table $table): Table
    {
        return CertificatesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificates::route('/'),
        ];
    }
}

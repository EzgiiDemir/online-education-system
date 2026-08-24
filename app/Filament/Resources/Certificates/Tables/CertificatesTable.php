<?php

namespace App\Filament\Resources\Certificates\Tables;

use App\Filament\Actions\ViewAuditLogAction;
use App\Models\Certificate;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('certificate_number')->label('Sertifika no')->searchable()->copyable(),
                TextColumn::make('enrollment.user.name')->label('Sahibi')->searchable()->sortable(),
                TextColumn::make('enrollment.course.title')->label('Eğitim')->searchable(),
                TextColumn::make('issued_at')->label('Veriliş')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->state(fn (Certificate $record) => $record->status())
                    ->formatStateUsing(fn (string $state) => ['active' => 'Geçerli', 'expired' => 'Süresi doldu', 'revoked' => 'İptal edildi'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'warning',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('revoked_reason')->label('İptal gerekçesi')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(['active' => 'Geçerli', 'expired' => 'Süresi doldu', 'revoked' => 'İptal edildi'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'revoked' => $query->whereNotNull('revoked_at'),
                        'active' => $query->whereNull('revoked_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('download')->label('İndir')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->url(fn (Certificate $record) => route('certificates.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Certificate $record) => ! $record->isRevoked()),
                Action::make('verify')->label('Doğrulama sayfası')->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                    ->url(fn (Certificate $record) => route('certificates.verify', $record->certificate_number))
                    ->openUrlInNewTab(),
                Action::make('revoke')->label('İptal et')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (Certificate $record) => ! $record->isRevoked())
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->label('İptal gerekçesi')->required()->maxLength(255),
                    ])
                    ->action(function (Certificate $record, array $data) {
                        $record->revoke(Auth::user(), $data['reason']);
                        Notification::make()->title('Sertifika iptal edildi')->success()->send();
                    }),
                ViewAuditLogAction::make(),
            ]);
    }
}

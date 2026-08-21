<?php

namespace App\Filament\Resources\Courses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instructor.name')->label('Eğitmen')->searchable()->sortable(),
                TextColumn::make('title')
                    ->label('Eğitim')
                    ->searchable(),
                TextColumn::make('delivery_type')->label('Tür')->badge()->formatStateUsing(fn ($state) => $state === 'online' ? 'Online' : 'Offline')
                    ->searchable(),
                TextColumn::make('enrollments_count')->label('Kayıt')->counts('enrollments')->badge(),
                TextColumn::make('language')->label('Dil')->badge()->formatStateUsing(fn ($state) => $state === 'en' ? 'English' : 'Türkçe'),
                TextColumn::make('status')->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Taslak', 'published' => 'Yayında', 'completed' => 'Tamamlandı'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'published' => 'success',
                        'completed' => 'gray',
                        default => 'warning',
                    })
                    ->searchable(),
                TextColumn::make('category')->label('Kategori')->badge()
                    ->formatStateUsing(fn ($state) => ['required' => 'Zorunlu', 'optional' => 'İsteğe bağlı', 'compliance' => 'Uyumluluk', 'onboarding' => 'Oryantasyon', 'professional_development' => 'Mesleki gelişim'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'compliance' => 'danger',
                        'required' => 'warning',
                        'onboarding' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('guest_instructor_name')->label('Misafir eğitimci')->placeholder('Yok')->toggleable(),
                TextColumn::make('certificate_enabled')->label('Sertifika')->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Pasif')->badge(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(['draft' => 'Taslak', 'published' => 'Yayında', 'completed' => 'Tamamlandı']),
                SelectFilter::make('delivery_type')->label('Tür')->options(['online' => 'Online', 'offline' => 'Offline']),
                SelectFilter::make('category')->label('Kategori')->options(['required' => 'Zorunlu', 'optional' => 'İsteğe bağlı', 'compliance' => 'Uyumluluk', 'onboarding' => 'Oryantasyon', 'professional_development' => 'Mesleki gelişim']),
                SelectFilter::make('instructor_id')->label('Eğitmen')->relationship('instructor', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

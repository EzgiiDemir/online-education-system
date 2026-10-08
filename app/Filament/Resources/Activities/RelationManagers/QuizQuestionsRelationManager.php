<?php

namespace App\Filament\Resources\Activities\RelationManagers;

use App\Models\Activity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Before this existed, there was no UI at all for authoring quiz content --
 * CourseBuilderController's doc comment claimed "quiz question authoring
 * stays admin-only via Filament", but no resource for it actually existed
 * anywhere. A "quiz" activity could be created, but no one could ever add a
 * single question to it without writing raw DB inserts. This is the fix.
 */
class QuizQuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'quizQuestions';

    protected static ?string $title = 'Sınav soruları';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Activity && $ownerRecord->type === 'quiz';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('question')->label(__('Soru metni'))
                    ->required()
                    ->columnSpanFull(),
                Select::make('type')->label(__('Soru tipi'))
                    ->options([
                        'single_choice' => 'Tek seçim',
                        'multi_choice' => 'Çoklu seçim',
                        'true_false' => 'Doğru / Yanlış',
                    ])
                    ->default('single_choice')
                    ->required(),
                TextInput::make('sort_order')->label(__('Sıra'))->numeric()->default(0),
                Repeater::make('options')->label(__('Seçenekler'))
                    ->relationship()
                    ->schema([
                        TextInput::make('label')->label(__('Seçenek metni'))->required(),
                        Toggle::make('is_correct')->label(__('Doğru cevap'))->inline(false),
                    ])
                    ->columns(2)
                    ->reorderable('sort_order')
                    ->defaultItems(2)
                    ->addActionLabel('Seçenek ekle')
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('question')->label(__('Soru metni'))->columnSpanFull(),
                TextEntry::make('type')->label(__('Soru tipi'))
                    ->formatStateUsing(fn (string $state) => ['single_choice' => 'Tek seçim', 'multi_choice' => 'Çoklu seçim', 'true_false' => 'Doğru / Yanlış'][$state] ?? $state),
                RepeatableEntry::make('options')->label(__('Seçenekler'))
                    ->schema([
                        TextEntry::make('label')->label(__('Seçenek'))->inlineLabel(false),
                        TextEntry::make('is_correct')->label(__('Doğru mu?'))
                            ->formatStateUsing(fn (bool $state) => $state ? '✓ Doğru' : '—')
                            ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('question')->label(__('Soru'))->limit(60)->searchable(),
                TextColumn::make('type')->label(__('Tip'))->badge()
                    ->formatStateUsing(fn (string $state) => ['single_choice' => 'Tek seçim', 'multi_choice' => 'Çoklu seçim', 'true_false' => 'Doğru/Yanlış'][$state] ?? $state),
                TextColumn::make('options_count')->label(__('Seçenek sayısı'))->counts('options')->badge(),
                TextColumn::make('sort_order')->label(__('Sıra')),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('Önizle')),
                EditAction::make()->label(__('Düzenle')),
                DeleteAction::make()->label(__('Sil')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

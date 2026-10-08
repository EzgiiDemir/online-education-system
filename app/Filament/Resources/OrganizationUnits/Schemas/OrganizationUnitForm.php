<?php

namespace App\Filament\Resources\OrganizationUnits\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganizationUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Birim adı'))->required()->maxLength(160),
                Select::make('parent_id')->label(__('Üst birim'))
                    ->relationship('parent', 'name', modifyQueryUsing: function ($query, $record) {
                        if (! $record) {
                            return $query;
                        }

                        // A unit can't be its own parent, nor a descendant of
                        // itself -- either would create a cycle in the tree.
                        // descendantIds() already includes the record's own
                        // id, so one exclusion covers both cases.
                        return $query->whereKeyNot($record->descendantIds());
                    })
                    ->searchable()
                    ->preload()
                    ->helperText(__('Boş bırakılırsa en üst seviye birim olur.')),
            ]);
    }
}

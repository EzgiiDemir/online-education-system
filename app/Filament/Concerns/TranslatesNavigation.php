<?php

namespace App\Filament\Concerns;

use UnitEnum;

/**
 * Filament resource/page chrome (nav group, nav label) is authored in
 * Turkish. This wraps those strings through __() so lang/en.json can
 * translate them when the session locale is English.
 */
trait TranslatesNavigation
{
    public static function getNavigationLabel(): string
    {
        $label = parent::getNavigationLabel();

        return is_string($label) && $label !== '' ? __($label) : $label;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }

    /**
     * Page titles and buttons ("Materyal düzenle", "Yeni kullanıcı") use the
     * model label. Without one Filament falls back to the class name, which
     * put English words such as "Material" into the Turkish panel.
     */
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    /**
     * Filament would derive the plural with English rules ("materyals");
     * the Turkish plural already exists as the navigation label.
     */
    public static function getPluralModelLabel(): string
    {
        return isset(static::$pluralModelLabel) ? __(static::$pluralModelLabel) : static::getNavigationLabel();
    }
}

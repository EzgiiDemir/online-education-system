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
}

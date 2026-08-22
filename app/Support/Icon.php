<?php

namespace App\Support;

class Icon
{
    public static function svg(string $name, int $size = 20, string $extraClass = ''): string
    {
        $path = resource_path('svg/lucide/'.$name.'.svg');
        $svg = is_file($path) ? file_get_contents($path) : '';
        $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg ?: '');
        $svg = trim(preg_replace('/\s(width|height|class)="[^"]*"/', '', $svg));

        return \Illuminate\Support\Str::replaceFirst(
            '<svg',
            '<svg class="icon '.$extraClass.'" width="'.$size.'" height="'.$size.'" aria-hidden="true" focusable="false"',
            $svg
        );
    }
}

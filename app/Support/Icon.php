<?php

namespace App\Support;

class Icon
{
    public static function svg(string $name, int $size = 20, string $extraClass = ''): string
    {
        $path = resource_path('svg/lucide/'.$name.'.svg');
        $svg = is_file($path) ? file_get_contents($path) : '';
        $svg = trim(preg_replace('/<!--.*?-->\s*/s', '', $svg ?: ''));

        // Only strip width/height/class from the root <svg> tag — a blanket
        // replace also hit nested elements (e.g. the mic icon's <rect>),
        // dropping their size and making the icon render broken/incomplete.
        $svg = preg_replace_callback('/<svg\b[^>]*>/', function (array $m): string {
            return preg_replace('/\s(width|height|class)="[^"]*"/', '', $m[0]);
        }, $svg, 1);

        return \Illuminate\Support\Str::replaceFirst(
            '<svg',
            '<svg class="icon '.$extraClass.'" width="'.$size.'" height="'.$size.'" aria-hidden="true" focusable="false"',
            $svg
        );
    }
}

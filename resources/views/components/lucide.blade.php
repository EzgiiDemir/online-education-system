@props(['name', 'size' => 20])
@php
    $path = resource_path('svg/lucide/'.$name.'.svg');
    $svg = is_file($path) ? file_get_contents($path) : '';
    $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg ?: '');
    $svg = trim(preg_replace('/\s(width|height|class)="[^"]*"/', '', $svg));
    $extraClass = e($attributes->get('class', ''));
@endphp
{!! \Illuminate\Support\Str::replaceFirst('<svg', '<svg class="icon '.$extraClass.'" width="'.(int) $size.'" height="'.(int) $size.'" aria-hidden="true" focusable="false"', $svg) !!}

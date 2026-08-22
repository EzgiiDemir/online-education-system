@props(['name', 'size' => 20])
{!! \App\Support\Icon::svg($name, (int) $size, e($attributes->get('class', ''))) !!}

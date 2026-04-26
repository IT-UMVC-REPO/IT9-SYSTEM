@props([
    'id',
    'height' => 280,
    'class' => '',
])

<div
    id="{{ $id }}"
    role="img"
    aria-label="{{ $attributes->get('aria-label', 'Chart') }}"
    wire:ignore
    {{ $attributes->class(['w-full', $class])->except(['aria-label']) }}
    style="height: {{ $height }}px; min-height: {{ $height }}px;"
></div>

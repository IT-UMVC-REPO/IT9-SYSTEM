@props([
    'model',
    'value' => 0,
    'min' => 0,
    'max' => 999999,
    'step' => 1,
    'name' => null,
    'suffix' => null,
    'placeholder' => null,
    'inputClass' => 'brand-stepper-input h-11 w-28 tabular-nums',
    'required' => false,
    'autofocus' => false,
    'ariaLabel' => null,
])

@php
    $minValue = (int) $min;
    $maxValue = (int) $max;
    $stepValue = (int) $step;
    $numericValue = is_numeric($value) ? (int) $value : $minValue;
@endphp

<div
    {{ $attributes->class('inline-flex items-center gap-2') }}
    x-data="sukiQuantityStepper({
        value: @js($numericValue),
        min: @js($minValue),
        max: @js($maxValue),
        sync: (value) => $wire.$set(@js($model), String(value), false),
        commit: (value) => $wire.$set(@js($model), String(value)),
    })"
>
    <button
        type="button"
        class="brand-stepper-button"
        aria-label="{{ __('Decrease value') }}"
        :disabled="!canDecrement"
        x-on:pointerdown.prevent="start(-{{ $stepValue }})"
        x-on:pointerup.window="stop"
        x-on:pointerleave="stop"
        x-on:keydown.enter.prevent="start(-{{ $stepValue }})"
        x-on:keyup.enter.window="stop"
        x-on:keydown.space.prevent="start(-{{ $stepValue }})"
        x-on:keyup.space.window="stop"
    >
        <i class="fa-solid fa-minus text-xs"></i>
    </button>

    <div class="relative">
        <input
            type="number"
            min="{{ $minValue }}"
            max="{{ $maxValue }}"
            step="{{ $stepValue }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
            class="{{ $inputClass }} {{ $suffix ? 'pr-14' : '' }}"
            x-model="value"
            x-on:input="syncFromInput"
            x-on:change="commitNow"
            x-on:blur="commitNow"
        >

        @if ($suffix)
            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-neutral-400 dark:text-zinc-500">
                {{ $suffix }}
            </span>
        @endif
    </div>

    <button
        type="button"
        class="brand-stepper-button"
        aria-label="{{ __('Increase value') }}"
        :disabled="!canIncrement"
        x-on:pointerdown.prevent="start({{ $stepValue }})"
        x-on:pointerup.window="stop"
        x-on:pointerleave="stop"
        x-on:keydown.enter.prevent="start({{ $stepValue }})"
        x-on:keyup.enter.window="stop"
        x-on:keydown.space.prevent="start({{ $stepValue }})"
        x-on:keyup.space.window="stop"
    >
        <i class="fa-solid fa-plus text-xs"></i>
    </button>
</div>

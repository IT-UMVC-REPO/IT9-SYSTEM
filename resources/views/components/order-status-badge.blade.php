@props(['status'])

@php
    $status = $status instanceof \App\Enums\OrderStatus ? $status : \App\Enums\OrderStatus::from((string) $status);
    $classes = match ($status) {
        \App\Enums\OrderStatus::Pending => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300',
        \App\Enums\OrderStatus::Confirmed => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/25 dark:bg-sky-500/10 dark:text-sky-300',
        \App\Enums\OrderStatus::Preparing => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/25 dark:bg-indigo-500/10 dark:text-indigo-300',
        \App\Enums\OrderStatus::Ready => 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-500/25 dark:bg-teal-500/10 dark:text-teal-300',
        \App\Enums\OrderStatus::Delivered => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300',
        \App\Enums\OrderStatus::Cancelled => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] transition-all duration-200 {$classes}"]) }}>
    {{ __(Str::headline($status->value)) }}
</span>

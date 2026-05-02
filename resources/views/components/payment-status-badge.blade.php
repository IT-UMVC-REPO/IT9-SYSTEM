@props(['status', 'label' => null])

@php
    $status = $status instanceof \App\Enums\PaymentStatus ? $status : \App\Enums\PaymentStatus::from((string) $status);
    $classes = match ($status) {
        \App\Enums\PaymentStatus::Pending => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300',
        \App\Enums\PaymentStatus::Paid => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300',
        \App\Enums\PaymentStatus::Failed => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] {$classes}"]) }}>
    {{ $label ?? __(Str::headline($status->value)) }}
</span>

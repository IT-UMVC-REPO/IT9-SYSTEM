@props(['vendor', 'showFollow' => true, 'compact' => false])

@php
    $isApproved = $vendor->status === \App\Enums\VendorStatus::Approved;
    $vendorUrl = $isApproved ? route('shop.vendors.show', $vendor) : null;
@endphp

<article {{ $attributes->merge(['class' => 'group relative flex h-full flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-black/5 transition duration-300 hover:-translate-y-1 hover:shadow-xl dark:bg-zinc-900 dark:ring-white/10']) }}>

    {{-- Image block --}}
    <div class="relative overflow-hidden">
        @if ($vendorUrl)
            <a href="{{ $vendorUrl }}" wire:navigate class="block">
        @else
            <div class="block">
        @endif

        <img
            src="{{ $vendor->store_image_url }}"
            alt="{{ $vendor->store_name }}"
            class="aspect-[3/2] w-full object-cover transition duration-700 group-hover:scale-[1.04]"
        >

        {{-- Gradient overlay --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>

        {{-- Store name over image --}}
        <h2 class="absolute bottom-4 left-4 right-4 font-serif text-lg font-bold leading-snug text-white drop-shadow-sm line-clamp-2">
            {{ $vendor->store_name }}
        </h2>

        @if ($vendorUrl)
            </a>
        @else
            </div>
        @endif

        {{-- Listing count badge --}}
        <div class="absolute left-3 top-3">
            <span class="inline-flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-neutral-700 shadow backdrop-blur-sm dark:bg-zinc-900/80 dark:text-zinc-200">
                <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand-500)]"></span>
                {{ trans_choice(':count listing|:count listings', (int) ($vendor->active_products_count ?? 0), ['count' => (int) ($vendor->active_products_count ?? 0)]) }}
            </span>
        </div>

        {{-- Follow button --}}
        @if ($showFollow && $isApproved)
            <div class="absolute right-3 top-3">
                <livewire:vendor.follow-button :vendor="$vendor" :overlay="true" :key="'vendor-card-follow-'.$vendor->id" />
            </div>
        @endif
    </div>

    {{-- Content block --}}
    <div class="flex flex-1 flex-col gap-3 p-4">

        {{-- Description --}}
        <p class="line-clamp-2 text-sm leading-relaxed text-neutral-500 dark:text-zinc-400">
            {{ $vendor->store_description }}
        </p>

        {{-- Meta row --}}
        <div class="flex items-center justify-between gap-2">
            @if ($vendor->relationLoaded('user') && $vendor->user)
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[var(--brand-50)] text-[10px] font-bold text-[var(--brand-700)] dark:bg-zinc-800 dark:text-[var(--brand-300)]">
                        {{ mb_strtoupper(mb_substr($vendor->user->name, 0, 1)) }}
                    </span>
                    <span class="truncate text-xs font-medium text-neutral-600 dark:text-zinc-300">{{ $vendor->user->name }}</span>
                </div>
            @endif
            <x-vendor-status-badge :status="$vendor->status" class="shrink-0 !px-2 !py-0.5 !text-[10px] !tracking-[0.12em]" />
        </div>

        {{-- CTA --}}
        <div class="mt-auto pt-1">
            @if ($vendorUrl)
                <a
                    href="{{ $vendorUrl }}"
                    wire:navigate
                    class="group/btn relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-[var(--brand-600)] px-4 py-2.5 text-sm font-semibold text-white transition duration-200 hover:bg-[var(--brand-700)] active:scale-[0.98] dark:bg-[var(--brand-500)] dark:hover:bg-[var(--brand-400)]"
                >
                    {{ __('Visit stall') }}
                    <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover/btn:translate-x-0.5"></i>
                </a>
            @else
                <span class="flex w-full items-center justify-center rounded-xl border border-dashed border-stone-200 px-4 py-2.5 text-sm font-medium text-neutral-400 dark:border-white/10 dark:text-zinc-500">
                    {{ __('No longer active') }}
                </span>
            @endif
        </div>
    </div>

</article>
@props(['vendor', 'showFollow' => true, 'compact' => false])

@php
    $isApproved = $vendor->status === \App\Enums\VendorStatus::Approved;
    $vendorUrl = $isApproved ? route('shop.vendors.show', $vendor) : null;
@endphp

<article {{ $attributes->merge(['class' => 'group brand-panel relative flex h-full flex-col overflow-hidden rounded-[2rem] transition duration-300 hover:-translate-y-1 hover:shadow-xl']) }}>
    <div class="relative overflow-hidden">
        @if ($vendorUrl)
            <a href="{{ $vendorUrl }}" wire:navigate class="block">
        @else
            <div class="block">
        @endif
            <img
                src="{{ $vendor->store_image_url }}"
                alt="{{ $vendor->store_name }}"
                class="{{ $compact ? 'aspect-[16/9]' : 'aspect-[4/3] sm:aspect-[16/9]' }} w-full object-cover transition duration-500 group-hover:scale-105"
            >
            <span class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/70 to-transparent"></span>
            <h2 class="brand-serif absolute bottom-5 left-5 right-5 line-clamp-2 text-2xl font-bold text-white">
                {{ $vendor->store_name }}
            </h2>
        @if ($vendorUrl)
            </a>
        @else
            </div>
        @endif

        <span @class([
            'absolute top-4 rounded-full bg-white/92 px-3 py-1 text-xs font-semibold text-neutral-900 shadow-sm backdrop-blur dark:bg-zinc-900/90 dark:text-zinc-100',
            'right-16' => $showFollow && $isApproved,
            'right-4' => ! ($showFollow && $isApproved),
        ])>
            {{ trans_choice(':count listing|:count listings', (int) ($vendor->active_products_count ?? 0), ['count' => (int) ($vendor->active_products_count ?? 0)]) }}
        </span>

        @if ($showFollow && $isApproved)
            <livewire:vendor.follow-button :vendor="$vendor" :overlay="true" :key="'vendor-card-follow-'.$vendor->id" />
        @endif
    </div>

    <div class="flex flex-1 flex-col p-5">
        <p class="line-clamp-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
            {{ $vendor->store_description }}
        </p>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            @if ($vendor->relationLoaded('user') && $vendor->user)
                <span class="text-sm font-semibold text-neutral-800 dark:text-zinc-200">{{ $vendor->user->name }}</span>
            @endif
            <x-vendor-status-badge :status="$vendor->status" />
        </div>

        @if ($vendorUrl)
            <a href="{{ $vendorUrl }}" wire:navigate class="brand-button-secondary mt-auto w-full">
                {{ __('Visit stall') }}
            </a>
        @else
            <span class="mt-auto inline-flex w-full items-center justify-center rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-semibold text-neutral-500 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-400">
                {{ __('No longer active') }}
            </span>
        @endif
    </div>
</article>

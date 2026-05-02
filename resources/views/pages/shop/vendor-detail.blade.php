<x-layouts::app :title="$vendorProfile->store_name">
    @if ($isOwnStall ?? false)
        <div class="mx-auto max-w-[1500px] px-4 pt-6 sm:px-6 lg:px-8">
            <div class="brand-soft-surface rounded-[1.75rem] border px-5 py-4 text-sm font-semibold text-[var(--brand-800)] dark:text-[var(--brand-200)]">
                <i class="fa-solid fa-store mr-2"></i>
                {{ __("You're viewing your own storefront - customers see it exactly like this.") }}
            </div>
        </div>
    @endif

    <section class="relative min-h-[380px] overflow-hidden border-b text-white" style="border-color: oklch(from var(--brand-900) l c h / 0.12);">
        <div class="absolute inset-0">
            <img
                src="{{ $vendorProfile->store_image_url }}"
                alt="{{ $vendorProfile->store_name }}"
                class="h-full w-full object-cover"
            >
            <div class="absolute inset-0" style="background: linear-gradient(to right, color-mix(in oklab, var(--brand-950) 75%, black 25%) 0%, color-mix(in oklab, var(--brand-700) 78%, black 22%) 45%, rgba(0,0,0,0.18) 100%);"></div>
            <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(0,0,0,0.16) 0%, rgba(0,0,0,0.08) 45%, rgba(0,0,0,0.3) 100%);"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
            <a
                href="{{ route('shop.vendors') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15"
            >
                <i class="fa-solid fa-arrow-left text-xs"></i>
                {{ __('Back to stalls') }}
            </a>

            <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-end">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            {{ __('Approved stall') }}
                        </span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            {{ trans_choice(':count active listing|:count active listings', $activeProductCount, ['count' => $activeProductCount]) }}
                        </span>
                    </div>

                    <h1 class="brand-serif mt-5 text-4xl font-bold sm:text-5xl lg:text-6xl">{{ $vendorProfile->store_name }}</h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-white/90">{{ $vendorProfile->store_description }}</p>
                    <p class="mt-4 text-sm font-medium text-white/80">{{ __('Managed by :name', ['name' => $vendorProfile->user->name]) }}</p>
                </div>

                <div class="rounded-[2rem] border border-white/15 bg-white/10 p-6 backdrop-blur-sm">
                    @if ($isOwnStall ?? false)
                        <div class="rounded-[1.5rem] border border-white/15 bg-white/10 px-4 py-3 text-sm font-semibold text-white">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            {{ __('This is your stall') }}
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <livewire:vendor.follow-button :vendor="$vendorProfile" :key="'vendor-storefront-follow-'.$vendorProfile->id" />
                            <p class="text-sm font-semibold text-white">
                                {{ $isFavorited ? __('Following this stall') : __('Follow this stall') }}
                            </p>
                        </div>

                        <a
                            href="{{ route('messages.conversation', ['conversationReference' => $vendorProfile->user_id]) }}"
                            wire:navigate
                            class="brand-button-primary mt-5 w-full"
                        >
                            {{ __('Message vendor') }}
                        </a>
                    @endif

                    @if (! ($isOwnStall ?? false) && in_array(auth()->user()?->effectiveMarketplaceRole()->value, ['customer', 'vendor'], true))
                        <flux:modal.trigger name="report-user">
                            <flux:button
                                variant="ghost"
                                type="button"
                                class="mt-3 w-full justify-center border border-white/15 bg-white/5 text-rose-100 hover:bg-rose-500/10 hover:text-white dark:text-rose-200"
                            >
                                <i class="fa-solid fa-flag text-xs"></i>
                                {{ __('Report this vendor') }}
                            </flux:button>
                        </flux:modal.trigger>

                        <livewire:report.report-modal
                            :reported-user-id="$vendorProfile->user_id"
                            :reporter-role="auth()->user()?->effectiveMarketplaceRole()->value"
                            :order-id="null"
                        />
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-[1500px] gap-8 px-4 py-8 sm:px-6 lg:px-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <span class="brand-kicker">{{ __('Storefront') }}</span>
                    <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Available products') }}</h2>
                </div>
            </div>

            @if ($products->isNotEmpty())
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach ($products as $product)
                        <article class="brand-panel flex h-full flex-col overflow-hidden p-5">
                            <a href="{{ route('shop.products.show', $product) }}" wire:navigate class="overflow-hidden rounded-[1.75rem] bg-stone-100 dark:bg-zinc-800">
                                <img
                                    src="{{ $product->image_url }}"
                                    alt="{{ $product->name }}"
                                    class="aspect-[5/4] w-full object-cover transition duration-300 hover:scale-[1.02]"
                                >
                            </a>

                            <div class="flex flex-1 flex-col pt-5">
                                <div class="flex items-start justify-between gap-4">
                                    <span class="brand-badge">{{ $product->category->name }}</span>
                                    <span class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">₱{{ number_format((float) $product->price, 2) }}</span>
                                </div>

                                <a href="{{ route('shop.products.show', $product) }}" wire:navigate class="mt-4 block text-2xl font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ $product->name }}
                                </a>

                                <div class="mt-auto pt-6">
                                    @if ($isOwnStall ?? false)
                                        <span class="inline-flex w-full items-center justify-center rounded-xl border border-[var(--brand-200)] bg-[var(--brand-50)] px-4 py-3 text-sm font-semibold text-[var(--brand-700)] dark:border-[var(--brand-500)] dark:bg-[color:oklch(from_var(--brand-500)_l_c_h_/_0.14)] dark:text-[var(--brand-300)]">
                                            {{ __('Your listing') }}
                                        </span>
                                    @else
                                        <a href="{{ route('shop.products.show', $product) }}" wire:navigate class="brand-button-secondary w-full">
                                            {{ __('View product') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div>
                        {{ $products->onEachSide(1)->links('layouts.app.paginate') }}
                    </div>
                @endif
            @else
                <div class="brand-panel px-6 py-16 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                        <i class="fa-solid fa-box-open text-xl"></i>
                    </span>
                    <h3 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No active listings yet') }}</h3>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('This stall is approved, but its active catalogue is currently empty.') }}
                    </p>
                </div>
            @endif
        </section>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="brand-panel p-6">
                <span class="brand-kicker">{{ __('Vendor info') }}</span>
                <div class="mt-5 space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Owner') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendorProfile->user->name }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Approved since') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ optional($vendorProfile->approved_at)->format('M j, Y') }}</span>
                    </div>
                    @if ($vendorProfile->vendor_address)
                        <div class="flex items-start justify-between gap-4">
                            <span class="shrink-0 text-neutral-500 dark:text-zinc-400">{{ __('Stall address') }}</span>
                            <span class="text-right font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendorProfile->vendor_address }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Active listings') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($activeProductCount) }}</span>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</x-layouts::app>

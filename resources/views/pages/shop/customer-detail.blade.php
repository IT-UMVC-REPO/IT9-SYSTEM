<x-layouts::app :title="$customer->name">
    <section class="relative min-h-[320px] overflow-hidden border-b text-white" style="border-color: oklch(from var(--brand-900) l c h / 0.12);">
        <div class="absolute inset-0" style="background: linear-gradient(to right, color-mix(in oklab, var(--brand-950) 78%, black 22%) 0%, color-mix(in oklab, var(--brand-700) 76%, black 24%) 45%, rgba(0,0,0,0.22) 100%);"></div>

        <div class="relative z-10 mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
            <a
                href="{{ auth()->user()?->effectiveMarketplaceRole()->value === 'vendor' ? route('vendor.orders') : route('shop.home') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15"
            >
                <i class="fa-solid fa-arrow-left text-xs"></i>
                {{ __('Back') }}
            </a>

            <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-end">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            {{ __('Customer profile') }}
                        </span>

                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            {{ trans_choice(':count order placed|:count orders placed', $ordersPlacedCount, ['count' => $ordersPlacedCount]) }}
                        </span>
                    </div>

                    <h1 class="brand-serif suki-reveal mt-5 text-4xl font-bold sm:text-5xl lg:text-6xl">{{ $customer->name }}</h1>
                    <p class="suki-reveal mt-5 max-w-2xl text-base leading-8 text-white/90" style="transition-delay: 80ms">
                        {{ $customer->address ?: __('No customer bio is available. Reach out via message for delivery details and preferences.') }}
                    </p>
                </div>

                <div class="suki-reveal rounded-4xl border border-white/15 bg-white/10 p-6 backdrop-blur-sm" style="transition-delay: 160ms">
                    @if ($canVendorStar)
                        <div class="flex items-center gap-3">
                            <livewire:customer.star-button :customer="$customer" :key="'customer-star-'.$customer->id" />
                            <p class="text-sm font-semibold text-white">
                                {{ $isStarredByVendor ? __('Customer is in your valued list') : __('Mark this customer as valued') }}
                            </p>
                        </div>
                    @endif

                    <a
                        href="{{ route('messages.conversation', ['conversationReference' => $customer->id]) }}"
                        wire:navigate
                        class="brand-button-primary mt-5 w-full transition-all duration-150 active:scale-[0.97]"
                    >
                        {{ __('Message customer') }}
                    </a>

                    @if (auth()->user()?->effectiveMarketplaceRole()->value === 'vendor')
                        <flux:modal.trigger name="report-user">
                            <flux:button
                                variant="ghost"
                                type="button"
                                class="mt-3 w-full justify-center border border-white/15 bg-white/5 text-rose-100 hover:bg-rose-500/10 hover:text-white dark:text-rose-200"
                            >
                                <i class="fa-solid fa-flag text-xs"></i>
                                {{ __('Report this customer') }}
                            </flux:button>
                        </flux:modal.trigger>

                        <livewire:report.report-modal
                            :reported-user-id="$customer->id"
                            reporter-role="vendor"
                            :order-id="null"
                        />
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-[1500px] gap-8 px-4 py-8 sm:px-6 lg:px-8 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <div class="brand-panel suki-reveal p-6" style="transition-delay: 100ms">
                <span class="brand-kicker">{{ __('Profile details') }}</span>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <article class="brand-panel-muted p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Name') }}</p>
                        <p class="mt-3 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $customer->name }}</p>
                    </article>
                    <article class="brand-panel-muted p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Orders placed') }}</p>
                        <p class="mt-3 text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($ordersPlacedCount) }}</p>
                    </article>
                    <article class="brand-panel-muted p-4 sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">{{ __('Address') }}</p>
                        <p class="mt-3 text-sm text-neutral-700 dark:text-zinc-300">{{ $customer->address ?: __('Not provided') }}</p>
                    </article>
                </div>
            </div>
        </section>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="brand-panel p-6">
                <span class="brand-kicker">{{ __('Interaction summary') }}</span>
                <div class="mt-5 space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Joined') }}</span>
                        <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $customer->created_at->format('M j, Y') }}</span>
                    </div>
                    @if ($canVendorStar)
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-500 dark:text-zinc-400">{{ __('Orders with your stall') }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ number_format($sharedOrderCount) }}</span>
                        </div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-layouts::app>

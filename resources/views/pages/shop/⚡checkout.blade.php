<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Final step') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Checkout') }}</h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Confirm your delivery details, review each vendor section, and prepare to pay cash when your order arrives.') }}
        </p>
    </section>

    @if ($errors->any())
        <div class="brand-panel-muted border border-amber-200 px-5 py-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ __('Please review the items in your order:') }}</p>
            <ul class="mt-2 space-y-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <form wire:submit="placeOrder" class="space-y-6">
            <div
                x-data="checkoutDeliveryMap({
                    initialLat: @js($delivery_lat),
                    initialLng: @js($delivery_lng),
                    defaultLat: 7.4479,
                    defaultLng: 125.8090,
                    defaultZoom: 14,
                    mapId: 'checkout-delivery-map',
                })"
                x-init="initMap()"
                x-on:livewire:navigating.window="destroyMap()"
                class="brand-panel space-y-5 p-6 sm:p-8"
            >
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">
                        {{ __('Delivery address') }}
                    </p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('Where should we deliver?') }}
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('Type your address below, or click the map to drop a pin — the address will fill in automatically.') }}
                    </p>
                </div>

                <div>
                    <flux:textarea
                        wire:model="delivery_address"
                        x-on:input.debounce.700ms="geocodeAddress($event.target.value)"
                        id="checkout-delivery-address"
                        rows="3"
                        required
                        :placeholder="__('Building/House No., Street, Subdivision/Village, Barangay, City')"
                    />
                    @error('delivery_address')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div
                        id="checkout-delivery-map"
                        wire:ignore
                        class="h-[240px] w-full overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 sm:h-[320px] dark:border-white/10 dark:bg-zinc-900"
                    ></div>

                    <input type="hidden" wire:model="delivery_lat" x-bind:value="hasPin ? lat : ''">
                    <input type="hidden" wire:model="delivery_lng" x-bind:value="hasPin ? lng : ''">

                    <p
                        x-cloak
                        x-show="geocoding"
                        class="mt-2 flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-zinc-400"
                    >
                        <span class="h-2 w-2 animate-pulse rounded-full bg-[var(--brand-600)]"></span>
                        {{ __('Looking up address…') }}
                    </p>

                    <button
                        type="button"
                        x-on:click="useCurrentLocation()"
                        class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] transition hover:underline dark:text-[var(--brand-400)]"
                    >
                        <i class="fa-solid fa-location-crosshairs text-xs"></i>
                        {{ __('Use my current location') }}
                    </button>
                </div>
            </div>

            <div class="brand-panel space-y-6 p-6 sm:p-8">
                <flux:textarea
                    wire:model="notes"
                    :label="__('Notes for the vendor')"
                    rows="3"
                    :placeholder="__('Optional handling requests or landmarks')"
                />
            </div>

            <div class="brand-panel space-y-5 p-6 sm:p-8">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Payment method') }}</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Cash on Delivery') }}</h2>
                </div>

                <input type="hidden" wire:model="payment_method" value="cod">

                <div class="rounded-3xl border border-[oklch(from_var(--brand-400)_l_c_h_/_0.32)] bg-[oklch(from_var(--brand-100)_l_c_h_/_0.6)] px-5 py-4 dark:border-[oklch(from_var(--brand-500)_l_c_h_/_0.2)] dark:bg-[oklch(from_var(--brand-500)_l_c_h_/_0.1)]">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--brand-600)] text-white shadow-xl shadow-[var(--brand-600)]/20">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-[var(--brand-900)] dark:text-[var(--brand-50)]">{{ __('Cash on Delivery confirmed') }}</p>
                            <p class="mt-1 text-xs font-medium text-[var(--brand-700)] dark:text-[var(--brand-400)]">{{ __('Settle payment directly when the order arrives at your address.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                class="brand-button-primary w-full"
            >
                <span wire:loading.remove wire:target="placeOrder">{{ __('Place order') }}</span>
                <span wire:loading wire:target="placeOrder">{{ __('Placing order...') }}</span>
            </button>
        </form>

        <aside class="self-start xl:sticky xl:top-24">
            <div class="brand-panel space-y-5 p-6 dark:border-white/10 dark:bg-zinc-900">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">{{ __('Order summary') }}</p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('From your cart') }}</h2>
                </div>

                <div class="space-y-4">
                    @foreach ($this->groupedCartItems as $vendorId => $items)
                        @php($vendor = $items->first()->product->vendor)

                        <section wire:key="checkout-vendor-{{ $vendorId }}" class="space-y-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</p>
                                    <p class="text-xs uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Vendor section') }}</p>
                                </div>
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                                    <img
                                        src="{{ $vendor->store_image_url }}"
                                        alt="{{ $vendor->store_name }}"
                                        class="h-full w-full object-cover"
                                        onerror="this.src='https://placehold.co/320x320/e7e5e4/9ca3af?text=Store'"
                                    >
                                </div>
                            </div>

                            @foreach ($items as $item)
                                <div wire:key="checkout-item-{{ $item->id }}" class="flex items-center gap-3">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                                        <img src="{{ $item->product->image }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                        <p class="text-xs text-neutral-400 dark:text-zinc-500">
                                            {{ $item->quantity }} {{ $item->product->unit->abbreviation() }}
                                            @if ($item->product->convertedQuantityLabel($item->quantity))
                                                ({{ __(':converted total', ['converted' => $item->product->convertedQuantityLabel($item->quantity)]) }})
                                            @endif
                                            × ₱{{ number_format((float) $item->product->price, 2) }}
                                        </p>
                                    </div>

                                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                        {{ __('₱:amount', ['amount' => number_format((float) $item->product->price * $item->quantity, 2)]) }}
                                    </p>
                                </div>
                            @endforeach

                            <div class="flex items-center justify-between gap-4 border-t border-stone-200 pt-3 text-sm dark:border-white/10">
                                <span class="font-medium text-neutral-500 dark:text-zinc-400">{{ __('Vendor subtotal') }}</span>
                                <span class="font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ __('₱:amount', ['amount' => number_format((float) $this->vendorSubtotals->get($vendorId, 0), 2)]) }}
                                </span>
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-800">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ __('Grand total') }}</span>
                        <span class="text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                            {{ __('₱:amount', ['amount' => number_format($this->orderTotal, 2)]) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                        {{ __('Delivery fees are agreed with each vendor.') }}
                    </p>
                </div>
            </div>
        </aside>
    </section>
</div>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Final step') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Checkout') }}</h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Confirm your fulfillment details, review each vendor section, and prepare to pay cash when the order is complete.') }}
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
            <div class="brand-panel suki-reveal space-y-5 p-6 sm:p-8" style="transition-delay: 0ms">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-400">
                        {{ __('Fulfillment') }}
                    </p>
                    <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('How would you like to receive it?') }}
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('Choose delivery to your address or collect directly from the vendor stall.') }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        wire:click="$set('fulfillment_method', 'delivery')"
                        @class([
                            'relative rounded-2xl p-4 text-left transition-all duration-200 active:scale-[0.97]',
                            'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white' => $fulfillment_method === 'delivery',
                            'border border-stone-200 bg-white text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $fulfillment_method !== 'delivery',
                        ])
                    >
                        @if ($fulfillment_method === 'delivery')
                            <span class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-white text-[var(--brand-600)]">
                                <i class="fa-solid fa-check text-[10px]"></i>
                            </span>
                        @endif
                        <i class="fa-solid fa-motorcycle"></i>
                        <span class="mt-3 block text-sm font-bold">{{ __('Deliver to my address') }}</span>
                        <span class="mt-1 block text-xs leading-5 opacity-80">{{ __('A rider brings the order to your selected pin.') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('fulfillment_method', 'self_pickup')"
                        @class([
                            'relative rounded-2xl p-4 text-left transition-all duration-200 active:scale-[0.97]',
                            'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white' => $fulfillment_method === 'self_pickup',
                            'border border-stone-200 bg-white text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $fulfillment_method !== 'self_pickup',
                        ])
                    >
                        @if ($fulfillment_method === 'self_pickup')
                            <span class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-white text-[var(--brand-600)]">
                                <i class="fa-solid fa-check text-[10px]"></i>
                            </span>
                        @endif
                        <i class="fa-solid fa-store"></i>
                        <span class="mt-3 block text-sm font-bold">{{ __("I'll pick it up") }}</span>
                        <span class="mt-1 block text-xs leading-5 opacity-80">{{ __('Collect from the vendor stall when it is ready.') }}</span>
                    </button>
                </div>

                @if ($fulfillment_method === 'delivery')
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
                        class="space-y-5"
                        wire:key="checkout-delivery-address-panel"
                    >
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
                                x-bind:class="hasPin ? 'opacity-100' : 'opacity-60'"
                                class="h-[240px] w-full overflow-hidden rounded-[1.5rem] border border-stone-200 bg-stone-100 transition-opacity duration-500 sm:h-[320px] dark:border-white/10 dark:bg-zinc-900"
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
                                class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-700)] transition-all duration-150 hover:gap-3 hover:underline active:scale-95 dark:text-[var(--brand-400)]"
                            >
                                <i class="fa-solid fa-location-crosshairs text-xs"></i>
                                {{ __('Use my current location') }}
                            </button>
                        </div>
                    </div>
                @else
                    @php
                        $pickupCustomer = auth()->user();
                        $pickupCustomerLat = is_numeric($delivery_lat) ? (float) $delivery_lat : null;
                        $pickupCustomerLng = is_numeric($delivery_lng) ? (float) $delivery_lng : null;
                        $hasPickupCustomerLocation = $pickupCustomerLat !== null
                            && $pickupCustomerLng !== null
                            && $pickupCustomerLat >= -90
                            && $pickupCustomerLat <= 90
                            && $pickupCustomerLng >= -180
                            && $pickupCustomerLng <= 180;
                        $pickupCustomerAddress = $delivery_address
                            ?: ($pickupCustomer?->address ?: __('Your saved location'));
                    @endphp

                    <div class="rounded-[1.5rem] border border-[oklch(from_var(--brand-400)_l_c_h_/_0.32)] bg-[oklch(from_var(--brand-100)_l_c_h_/_0.6)] p-5 dark:border-[oklch(from_var(--brand-500)_l_c_h_/_0.25)] dark:bg-[oklch(from_var(--brand-500)_l_c_h_/_0.12)]">
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--brand-600)] text-white">
                                <i class="fa-solid fa-store"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-[var(--brand-900)] dark:text-[var(--brand-50)]">{{ __('Pickup from the vendor stall') }}</p>
                                <p class="mt-1 text-xs font-medium leading-5 text-[var(--brand-700)] dark:text-[var(--brand-300)]">
                                    {{ __('Visit the stall directly and show your order number at pickup.') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ($this->pickupVendors as $vendor)
                                @php
                                    $hasPickupVendorLocation = $vendor->hasLocation();
                                @endphp

                                <div wire:key="checkout-pickup-vendor-{{ $vendor->id }}" class="rounded-2xl border border-white/70 bg-white/70 p-4 dark:border-white/10 dark:bg-zinc-900/60">
                                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendor->store_name }}</p>
                                    <p class="mt-1 text-sm leading-6 text-neutral-600 dark:text-zinc-300">
                                        {{ $vendor->vendor_address ?: __('Self-pickup at vendor stall') }}
                                    </p>

                                    @if ($hasPickupCustomerLocation || $hasPickupVendorLocation)
                                        <div
                                            x-data="sukiUnifiedOrderMap({
                                                mapId: 'checkout-self-pickup-map-{{ $vendor->id }}',
                                                customerLat: @js($hasPickupCustomerLocation ? $pickupCustomerLat : null),
                                                customerLng: @js($hasPickupCustomerLocation ? $pickupCustomerLng : null),
                                                customerAddress: @js($pickupCustomerAddress),
                                                vendorLat: @js($hasPickupVendorLocation ? (float) $vendor->lat : null),
                                                vendorLng: @js($hasPickupVendorLocation ? (float) $vendor->lng : null),
                                                vendorName: @js($vendor->store_name),
                                                vendorAddress: @js($vendor->vendor_address ?: __('Tagum City')),
                                                riderActive: false,
                                                riderLat: null,
                                                riderLng: null,
                                                orderId: null,
                                            })"
                                            x-init="init()"
                                            x-on:livewire:navigating.window="destroy()"
                                            class="mt-4 overflow-hidden rounded-[1.25rem] border border-stone-200 bg-white dark:border-white/10 dark:bg-zinc-950"
                                            wire:key="checkout-self-pickup-route-{{ $vendor->id }}"
                                        >
                                            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                                <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-neutral-500 dark:text-zinc-400">
                                                    <span class="text-[11px] uppercase tracking-[0.18em] text-[var(--brand-700)] dark:text-[var(--brand-300)]">{{ __('Pickup route') }}</span>
                                                    @if ($hasPickupCustomerLocation)
                                                        <span class="inline-flex items-center gap-2">
                                                            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                                                            {{ __('Your location') }}
                                                        </span>
                                                    @endif
                                                    @if ($hasPickupVendorLocation)
                                                        <span class="inline-flex items-center gap-2">
                                                            <span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                                                            {{ __('Vendor Stall') }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <p x-text="distanceLabel" class="min-h-5 text-xs font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]"></p>
                                            </div>

                                            <div id="checkout-self-pickup-map-{{ $vendor->id }}" wire:ignore class="h-[220px] w-full sm:h-[260px]"></div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="brand-panel suki-reveal space-y-6 p-6 sm:p-8" style="transition-delay: 100ms">
                <flux:textarea
                    wire:model="notes"
                    :label="__('Notes for the vendor')"
                    rows="3"
                    :placeholder="__('Optional handling requests or landmarks')"
                />
            </div>

            <div class="brand-panel suki-reveal space-y-5 p-6 sm:p-8" style="transition-delay: 200ms">
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
                            <p class="mt-1 text-xs font-medium text-[var(--brand-700)] dark:text-[var(--brand-400)]">
                                {{ $fulfillment_method === 'self_pickup'
                                    ? __('Settle payment directly when you collect from the vendor stall.')
                                    : __('Settle payment directly when the order arrives at your address.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="placeOrder"
                class="brand-button-primary w-full transition-all duration-200 hover:shadow-lg hover:shadow-[var(--brand-600)]/20 active:scale-[0.96]"
            >
                <span wire:loading.remove wire:target="placeOrder">{{ __('Place order') }}</span>
                <span wire:loading wire:target="placeOrder">{{ __('Placing order...') }}</span>
            </button>
        </form>

        <aside class="suki-reveal self-start xl:sticky xl:top-24" style="transition-delay: 300ms">
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
                                        loading="lazy"
                                    >
                                </div>
                            </div>

                            @foreach ($items as $item)
                                <div wire:key="checkout-item-{{ $item->id }}" class="flex items-center gap-3">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-900">
                                        <img src="{{ $item->product->image }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover" loading="lazy">
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $item->product->name }}</p>
                                        <p class="text-xs text-neutral-400 dark:text-zinc-500">
                                            {{ \App\Support\UnitFormatter::format($item->unitVariant?->unit ?? $item->product->unit, $item->quantity) }}
                                            @if (($conversion = ($item->unitVariant?->conversionFor($item->quantity) ?? $item->product->conversionFor($item->quantity))))
                                                ({{ __(':converted total', ['converted' => $conversion->convertedQuantityLabel()]) }})
                                            @endif
                                            × {{ \App\Support\UnitFormatter::currency($item->unitPrice()) }}
                                        </p>
                                    </div>

                                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                        {{ \App\Support\UnitFormatter::currency($item->lineTotal()) }}
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
                        {{ $fulfillment_method === 'self_pickup'
                            ? __('No rider fee is needed for pickup orders.')
                            : __('Delivery fees are agreed with each vendor.') }}
                    </p>
                </div>
            </div>
        </aside>
    </section>
</div>

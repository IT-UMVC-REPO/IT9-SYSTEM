<x-layouts::app :title="$product->name">
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">

        <a
            href="{{ route('shop.home') }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 transition hover:text-emerald-800 dark:text-emerald-400"
        >
            <flux:icon.arrow-left class="size-4" />
            Back to storefront
        </a>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]">

            {{-- Product image --}}
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                <div class="aspect-[4/3] overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                    <img
                        src="{{ $product->image }}"
                        alt="{{ $product->name }}"
                        class="h-full w-full object-cover"
                    >
                </div>
            </div>

            {{-- Product info --}}
            <div class="space-y-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            {{ $product->category->name }}
                        </span>
                        <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            {{ $product->stock_quantity }} in stock
                        </span>
                    </div>

                    <h1 class="mt-3 text-2xl font-semibold text-neutral-900 dark:text-white">
                        {{ $product->name }}
                    </h1>

                    <p class="mt-2.5 text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                        {{ $product->description }}
                    </p>

                    <div class="mt-5 rounded-lg border border-neutral-100 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-950">
                        <p class="text-xs text-neutral-400">Market price</p>
                        <p class="mt-1 text-3xl font-semibold text-neutral-900 dark:text-white">
                            PHP {{ number_format((float) $product->price, 2) }}
                        </p>
                    </div>
                </div>

                {{-- Vendor card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium text-neutral-400 uppercase tracking-wide">Sold by</p>
                    <h2 class="mt-1.5 text-lg font-semibold text-neutral-900 dark:text-white">
                        {{ $product->vendor->store_name }}
                    </h2>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ $product->vendor->store_description }}
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                            <p class="text-xs text-neutral-400">Category</p>
                            <p class="mt-0.5 text-sm font-medium text-neutral-800 dark:text-white">{{ $product->category->name }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                            <p class="text-xs text-neutral-400">Available</p>
                            <p class="mt-0.5 text-sm font-medium text-neutral-800 dark:text-white">{{ $product->stock_quantity }} units</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
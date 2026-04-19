<x-layouts::app :title="__('SukiMarket Storefront')">
    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">

        {{-- Page header: solid strip, no blobs --}}
        <section class="rounded-xl border border-emerald-200 bg-emerald-600 px-6 py-7 text-white dark:border-emerald-800 dark:bg-emerald-700">
            <p class="text-xs font-medium text-emerald-100">{{ now()->format('l, F j') }}</p>
            <h1 class="mt-1.5 text-2xl font-semibold">Fresh from the palengke</h1>
            <p class="mt-1 text-sm text-emerald-100">
                Browse approved vendors, discover market-day staples, and shop active listings.
            </p>
        </section>

        <section class="grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">

            {{-- Sidebar filters --}}
            <aside class="space-y-5">
                <form method="GET" action="{{ route('shop.home') }}" class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 class="text-sm font-semibold text-neutral-800 dark:text-white">Filter listings</h2>

                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Keyword</span>
                            <input
                                type="search"
                                name="search"
                                value="{{ $searchTerm }}"
                                placeholder="Try ampalaya, seafood…"
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-1 focus:ring-emerald-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-neutral-600 dark:text-neutral-400">Category</span>
                            <select
                                name="category"
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-1 focus:ring-emerald-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white"
                            >
                                <option value="">All categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>
                                        {{ $category->name }} ({{ $category->products_count }})
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row xl:flex-col">
                        <button
                            type="submit"
                            class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                        >
                            Apply filters
                        </button>

                        @if ($searchTerm !== '' || $selectedCategory !== null)
                            <a
                                href="{{ route('shop.home') }}"
                                class="rounded-lg border border-neutral-200 px-3 py-2 text-center text-sm font-medium text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            >
                                Clear
                            </a>
                        @endif
                    </div>
                </form>

                @if ($categories->isNotEmpty())
                    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <h2 class="text-sm font-semibold text-neutral-800 dark:text-white">Categories</h2>
                        <ul class="mt-3 space-y-1">
                            @foreach ($categories as $category)
                                <li>
                                    <a
                                        href="{{ route('shop.home', array_filter(['category' => $category->id, 'search' => $searchTerm])) }}"
                                        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-neutral-700 transition hover:bg-emerald-50 hover:text-emerald-700 dark:text-neutral-300 dark:hover:bg-emerald-950 dark:hover:text-emerald-300 {{ $selectedCategory === $category->id ? 'bg-emerald-50 font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : '' }}"
                                    >
                                        <span>{{ $category->name }}</span>
                                        <span class="text-xs text-neutral-400">{{ $category->products_count }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>

            {{-- Main listings --}}
            <section>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Active listings</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            @if ($searchTerm !== '' || $selectedCategory !== null)
                                Filtered results
                            @else
                                All approved vendor listings
                            @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ $products->count() }} / {{ $products->total() }} products
                    </p>
                </div>

                @if ($products->isNotEmpty())
                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($products as $product)
                            <article class="group overflow-hidden rounded-xl border border-neutral-200 bg-white transition hover:border-emerald-300 hover:shadow-sm dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-emerald-700">
                                <a href="{{ route('shop.products.show', $product) }}" class="block">
                                    <div class="relative aspect-[4/3] overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                                        <img
                                            src="{{ $product->image }}"
                                            alt="{{ $product->name }}"
                                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                            loading="lazy"
                                        >
                                        <div class="absolute right-3 top-3">
                                            @if ($product->stock_quantity > 0)
                                                <span class="rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white">
                                                    {{ $product->stock_quantity }} left
                                                </span>
                                            @else
                                                <span class="rounded-md bg-amber-500 px-2 py-0.5 text-xs font-medium text-white">
                                                    Sold out
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </a>

                                <div class="p-4">
                                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $product->vendor->store_name }}</p>
                                    <a
                                        href="{{ route('shop.products.show', $product) }}"
                                        class="mt-1 block font-semibold text-neutral-900 transition group-hover:text-emerald-700 dark:text-white dark:group-hover:text-emerald-400"
                                    >
                                        {{ $product->name }}
                                    </a>
                                    <p class="mt-1.5 line-clamp-2 text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $product->description }}
                                    </p>

                                    <div class="mt-4 flex items-center justify-between gap-2">
                                        <span class="text-lg font-semibold text-neutral-900 dark:text-white">
                                            PHP {{ number_format((float) $product->price, 2) }}
                                        </span>
                                        <a
                                            href="{{ route('shop.products.show', $product) }}"
                                            class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-neutral-700 dark:text-neutral-300"
                                        >
                                            View
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($products->hasPages())
                        <div class="mt-6">
                            {{ $products->links() }}
                        </div>
                    @endif
                @else
                    <div class="mt-4 rounded-xl border border-dashed border-neutral-300 bg-neutral-50 px-6 py-14 text-center dark:border-neutral-700 dark:bg-neutral-900/50">
                        <flux:icon.magnifying-glass class="mx-auto size-8 text-neutral-400" />
                        <h3 class="mt-4 font-semibold text-neutral-900 dark:text-white">No results</h3>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            Try a different keyword or category.
                        </p>
                        @if ($searchTerm !== '' || $selectedCategory !== null)
                            <a
                                href="{{ route('shop.home') }}"
                                class="mt-4 inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                Clear filters
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        </section>
    </div>
</x-layouts::app>
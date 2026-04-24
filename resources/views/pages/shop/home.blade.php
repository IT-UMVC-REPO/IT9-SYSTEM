<x-layouts::app :title="__('SukiMarket Storefront')">
    @php
        $filterSummary = match (true) {
            $searchTerm !== '' && $selectedCategory !== null => 'You are narrowing the market by keyword and category for a faster shortlist.',
            $searchTerm !== '' => 'Keyword search is spotlighting listings that match what you are craving today.',
            $selectedCategory !== null => 'You are browsing one market aisle at a time for a cleaner, easier scan.',
            default => 'All approved stalls and live listings are open for browsing right now.',
        };
    @endphp

    <section class="relative min-h-[420px] overflow-hidden border-b border-emerald-900/10 text-white">
        <div class="absolute inset-0 z-0">
            <img
                src="https://static.tripzilla.ph/media/98742/conversions/Palengke-Tips-w1024.webp"
                alt=""
                aria-hidden="true"
                class="h-full w-full object-cover object-center"
            >
            <div class="absolute inset-0"
                style="background: linear-gradient(
                    to right,
                    #059669 0%,
                    #059669 30%,
                    rgba(5,150,105,0.85) 45%,
                    rgba(5,150,105,0.4) 65%,
                    rgba(5,150,105,0.1) 80%,
                    transparent 100%
                );"></div>
            <div class="absolute inset-0"
                style="background: linear-gradient(
                    to bottom,
                    rgba(0,0,0,0.18) 0%,
                    transparent 25%,
                    transparent 75%,
                    rgba(0,0,0,0.25) 100%
                );"></div>
        </div>
        <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-1/2 bg-[radial-gradient(circle_at_top_right,_rgb(255_255_255_/_0.24),_transparent_52%)]"></div>
        <div class="pointer-events-none absolute -left-20 top-10 z-10 h-56 w-56 rounded-full bg-white/12 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 right-[-4rem] z-10 h-72 w-72 rounded-full bg-lime-200/20 blur-3xl"></div>

        <div class="relative z-10 mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:px-8 lg:py-20">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-emerald-50">
                    <i class="fa-solid fa-store"></i>
                    Customer storefront
                </span>

                <h1 class="brand-serif mt-6 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl" style="text-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                    A brighter market floor for your next suki run.
                </h1>

                <p class="mt-5 max-w-2xl text-base leading-8 text-emerald-50/90">
                    Browse approved stalls, scan live listings faster, and move through the catalog in a storefront that feels open, welcoming, and easy to explore.
                </p>
            </div>
        </div>
    </section>

    @if ($popularVendors->isNotEmpty())
        <div class="border-b border-stone-200 bg-white">
            <div class="mx-auto max-w-[1500px] px-4 py-10 sm:px-6 lg:px-8">
                <div class="mb-6 flex items-end justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-600">
                            Marketplace
                        </p>
                        <h2 class="brand-serif mt-1 text-2xl font-bold text-neutral-900">
                            Popular vendors this week
                        </h2>
                    </div>
                    <a href="{{ route('shop.home') }}"
                        class="hidden text-sm font-medium text-emerald-700 hover:underline sm:block">
                        Browse all &rarr;
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    @foreach ($popularVendors as $vendor)
                        <div class="group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-stone-50 p-4 transition hover:border-emerald-200 hover:shadow-md">
                            <button
                                type="button"
                                aria-label="Follow {{ $vendor->store_name }}"
                                class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-400 transition hover:border-rose-300 hover:text-rose-500"
                                onclick="this.classList.toggle('!text-rose-500'); this.classList.toggle('!border-rose-400'); this.classList.toggle('!bg-rose-50');"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126
                                        -4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75
                                        3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                            </button>
                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-base font-bold text-emerald-700">
                                {{ strtoupper(substr($vendor->store_name, 0, 2)) }}
                            </div>
                            <p class="line-clamp-1 pr-6 text-sm font-semibold leading-snug text-neutral-900">
                                {{ $vendor->store_name }}
                            </p>
                            <p class="mt-1 flex-1 line-clamp-2 text-xs leading-5 text-neutral-400">
                                {{ $vendor->store_description }}
                            </p>
                            <div class="mt-3 flex items-center gap-1.5 border-t border-stone-200 pt-3">
                                <span class="text-xs font-semibold text-emerald-700">
                                    {{ $vendor->active_products_count }}
                                </span>
                                <span class="text-xs text-stone-400">
                                    {{ Str::plural('listing', $vendor->active_products_count) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-8 xl:grid-cols-[20rem_minmax(0,1fr)] 2xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="self-start scrollbar-none xl:sticky xl:top-[76px] xl:max-h-[calc(100vh-76px)] xl:overflow-y-auto">
                <form method="GET" action="{{ route('shop.home') }}"
                    class="brand-panel space-y-5 p-5">

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">
                            Search
                        </label>
                        <input
                            type="search"
                            name="search"
                            value="{{ $searchTerm }}"
                            placeholder="Try ampalaya or seafood"
                            class="brand-input"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">
                            Category
                        </label>
                        <select name="category" class="brand-select">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <optgroup label="{{ $category->name }}">
                                    <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>
                                        All {{ $category->name }}
                                    </option>
                                    @foreach ($category->children as $childCategory)
                                        <option value="{{ $childCategory->id }}" @selected($selectedCategory === $childCategory->id)>
                                            - {{ $childCategory->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">
                            Max price (PHP)
                        </label>
                        <select name="max_price" class="brand-select">
                            <option value="">Any price</option>
                            <option value="50" @selected(request('max_price') == '50')>Under &#8369;50</option>
                            <option value="100" @selected(request('max_price') == '100')>Under &#8369;100</option>
                            <option value="200" @selected(request('max_price') == '200')>Under &#8369;200</option>
                            <option value="500" @selected(request('max_price') == '500')>Under &#8369;500</option>
                            <option value="1000" @selected(request('max_price') == '1000')>Under &#8369;1,000</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">
                            Sort by
                        </label>
                        <select name="sort" class="brand-select">
                            <option value="" @selected(! request('sort'))>Recently added</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
                            <option value="name_asc" @selected(request('sort') === 'name_asc')>Name: A&ndash;Z</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2 pt-1">
                        <button type="submit" class="brand-button-primary w-full">Search</button>
                        @if ($searchTerm !== '' || $selectedCategory !== null || request('max_price') || request('sort'))
                            <a href="{{ route('shop.home') }}" class="brand-button-secondary w-full text-center">
                                Clear filters
                            </a>
                        @endif
                    </div>
                </form>
            </aside>

            <section>
                <div class="mb-6 flex items-center justify-between">
                    <p class="text-sm font-semibold text-stone-500">
                        {{ $products->total() }} {{ Str::plural('product', $products->total()) }}
                        {{ $selectedCategoryName ? 'in '.$selectedCategoryName : 'available' }}
                    </p>
                    {{-- @if ($searchTerm !== '' || $selectedCategory !== null)
                        <a href="{{ route('shop.home') }}" class="text-sm text-emerald-700 hover:underline">
                            Clear filters
                        </a>
                    @endif --}}
                </div>

                @if ($products->isNotEmpty())
                    <div class="grid gap-6 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($products as $product)
                            <article class="group flex h-full flex-col overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                                <a href="{{ route('shop.products.show', $product) }}" class="block">
                                    <div class="relative aspect-[5/4] overflow-hidden bg-stone-100">
                                        <img
                                            src="{{ $product->image }}"
                                            alt="{{ $product->name }}"
                                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                            loading="lazy"
                                        >

                                        <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4">
                                            <span class="rounded-full bg-white/92 px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm">
                                                {{ $product->category->name }}
                                            </span>

                                            @if ($product->stock_quantity > 0)
                                                <span class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm">
                                                    {{ $product->stock_quantity }} left
                                                </span>
                                            @else
                                                <span class="rounded-full bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm">
                                                    Sold out
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </a>

                                <div class="flex flex-1 flex-col p-6">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">{{ $product->vendor->store_name }}</p>
                                        <span class="text-sm font-semibold text-neutral-900">PHP {{ number_format((float) $product->price, 2) }}</span>
                                    </div>

                                    <a href="{{ route('shop.products.show', $product) }}" class="mt-3 block text-2xl font-semibold text-neutral-900 transition group-hover:text-emerald-700">
                                        {{ $product->name }}
                                    </a>

                                    <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-500">
                                        {{ $product->description }}
                                    </p>

                                    <div class="mt-auto pt-6">
                                        <a href="{{ route('shop.products.show', $product) }}" class="brand-button-secondary w-full">
                                            View product
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($products->hasPages())
                        <div class="mt-8">
                            {{ $products->onEachSide(1)->links('layouts.app.paginate') }}
                        </div>
                    @endif
                @else
                    <div class="brand-panel px-6 py-14 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400">
                            <i class="fa-solid fa-magnifying-glass text-xl"></i>
                        </span>
                        <h3 class="brand-serif mt-5 text-2xl font-bold text-neutral-900">No matching market finds yet</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-neutral-500">
                            Try a different keyword or category to discover more approved listings.
                        </p>
                        @if ($searchTerm !== '' || $selectedCategory !== null)
                            <a href="{{ route('shop.home') }}" class="brand-button-primary mt-5">
                                <span class="text-accent-foreground">Clear filters</span>
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        </section>
    </div>
</x-layouts::app>

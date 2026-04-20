<x-layouts::app :title="__('SukiMarket Storefront')">
    @php
        $selectedCategoryName = $selectedCategory !== null
            ? $categories->firstWhere('id', $selectedCategory)?->name
            : null;

        $filterSummary = match (true) {
            $searchTerm !== '' && $selectedCategory !== null => 'You are narrowing the market by keyword and category for a faster shortlist.',
            $searchTerm !== '' => 'Keyword search is spotlighting listings that match what you are craving today.',
            $selectedCategory !== null => 'You are browsing one market aisle at a time for a cleaner, easier scan.',
            default => 'All approved stalls and live listings are open for browsing right now.',
        };
    @endphp

    <section class="relative overflow-hidden border-b border-emerald-900/10 bg-gradient-to-br from-lime-300 via-emerald-500 to-green-900 text-white">
        <div class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,_rgb(255_255_255_/_0.24),_transparent_52%)]"></div>
        <div class="pointer-events-none absolute -left-20 top-10 h-56 w-56 rounded-full bg-white/12 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 right-[-4rem] h-72 w-72 rounded-full bg-lime-200/20 blur-3xl"></div>

        <div class="mx-auto grid max-w-[1500px] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)] lg:px-8 lg:py-16">
            <div class="max-w-3xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-emerald-50">
                    <i class="fa-solid fa-store"></i>
                    Customer storefront
                </span>

                <h1 class="brand-serif mt-6 text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                    A brighter market floor for your next suki run.
                </h1>

                <p class="mt-5 max-w-2xl text-base leading-8 text-emerald-50/90">
                    Browse approved stalls, scan live listings faster, and move through the catalog in a storefront that feels open, welcoming, and easy to explore.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/12 px-4 py-2 text-sm font-medium text-white">
                        <i class="fa-solid fa-circle-check text-emerald-100"></i>
                        Approved stalls only
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/12 px-4 py-2 text-sm font-medium text-white">
                        <i class="fa-solid fa-grip text-emerald-100"></i>
                        Roomier listing grid
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/12 px-4 py-2 text-sm font-medium text-white">
                        <i class="fa-solid fa-thumbtack text-emerald-100"></i>
                        Sticky filters and quick links
                    </span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                <div class="rounded-[1.75rem] border border-white/15 bg-white/10 p-5 backdrop-blur-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-50/80">Live listings</p>
                    <p class="mt-3 text-3xl font-semibold text-white">{{ $products->total() }}</p>
                    <p class="mt-2 text-sm leading-6 text-emerald-50/85">Fresh picks that are ready to browse right now.</p>
                </div>

                <div class="rounded-[1.75rem] border border-white/15 bg-white/10 p-5 backdrop-blur-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-50/80">Open aisles</p>
                    <p class="mt-3 text-3xl font-semibold text-white">{{ $categories->count() }}</p>
                    <p class="mt-2 text-sm leading-6 text-emerald-50/85">Categories with visible products from approved sellers.</p>
                </div>

                <div class="rounded-[1.75rem] border border-white/15 bg-white/10 p-5 backdrop-blur-sm sm:col-span-2">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 text-white">
                            <i class="fa-solid fa-sliders"></i>
                        </span>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-50/80">Browsing mode</p>
                            <p class="mt-2 text-sm leading-6 text-emerald-50/90">{{ $filterSummary }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-white/12 px-3 py-1.5 text-xs font-semibold text-white">
                            {{ $selectedCategoryName ?? 'All categories' }}
                        </span>
                        <span class="rounded-full bg-white/12 px-3 py-1.5 text-xs font-semibold text-white">
                            {{ $searchTerm !== '' ? 'Search: '.$searchTerm : 'No keyword filter' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-8 xl:grid-cols-[20rem_minmax(0,1fr)] 2xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="self-start xl:sticky xl:top-24">
                <div class="space-y-5">
                    <form method="GET" action="{{ route('shop.home') }}" class="brand-panel p-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                <i class="fa-solid fa-sliders text-lg"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-neutral-900">Filter the market</h2>
                                <p class="text-xs leading-5 text-neutral-400">Search the live catalog and jump straight into the right aisle.</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <label class="block">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">Keyword</span>
                                <input
                                    type="search"
                                    name="search"
                                    value="{{ $searchTerm }}"
                                    placeholder="Try ampalaya or seafood"
                                    class="brand-input"
                                >
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">Category</span>
                                <select name="category" class="brand-select">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected($selectedCategory === $category->id)>
                                            {{ $category->name }} ({{ $category->products_count }})
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="mt-6 flex flex-col gap-2">
                            <button type="submit" class="brand-button-primary w-full">Apply filters</button>

                            @if ($searchTerm !== '' || $selectedCategory !== null)
                                <a href="{{ route('shop.home') }}" class="brand-button-secondary w-full">
                                    Clear filters
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($categories->isNotEmpty())
                        <div class="brand-panel p-6">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-sm font-semibold text-neutral-900">Quick aisle links</h2>
                                    <p class="mt-1 text-xs leading-5 text-neutral-400">Pin yourself to the busiest categories in one tap.</p>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    {{ $categories->count() }} live
                                </span>
                            </div>

                            <ul class="mt-5 space-y-2">
                                @foreach ($categories as $category)
                                    <li>
                                        <a
                                            href="{{ route('shop.home', array_filter(['category' => $category->id, 'search' => $searchTerm])) }}"
                                            class="flex items-center justify-between gap-3 rounded-[1.25rem] border px-4 py-3 text-sm font-medium transition {{ $selectedCategory === $category->id ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-stone-200 text-neutral-600 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700' }}"
                                        >
                                            <span class="truncate">{{ $category->name }}</span>
                                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-neutral-400 shadow-sm">
                                                {{ $category->products_count }}
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </aside>

            <section>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-600">
                            {{ $searchTerm !== '' || $selectedCategory !== null ? 'Filtered market picks' : 'Live market picks' }}
                        </p>
                        <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 sm:text-4xl">Fresh finds with room to browse</h2>
                        <p class="mt-3 text-sm leading-7 text-neutral-500">
                            {{ $products->total() }} customer-visible product{{ $products->total() === 1 ? '' : 's' }} from approved stalls, laid out in a cleaner catalog that is easier to scan on mobile and desktop.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="brand-badge px-4 py-2 text-sm text-neutral-700">
                            <i class="fa-solid fa-layer-group text-emerald-600"></i>
                            {{ $products->count() }} on this page
                        </span>
                        @if ($selectedCategoryName)
                            <span class="brand-badge px-4 py-2 text-sm text-neutral-700">
                                <i class="fa-solid fa-tag text-emerald-600"></i>
                                {{ $selectedCategoryName }}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($products->isNotEmpty())
                    <div class="mt-8 grid gap-6 md:grid-cols-2 2xl:grid-cols-3">
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
                            {{ $products->links() }}
                        </div>
                    @endif
                @else
                    <div class="brand-panel mt-8 px-6 py-14 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400">
                            <i class="fa-solid fa-magnifying-glass text-xl"></i>
                        </span>
                        <h3 class="brand-serif mt-5 text-2xl font-bold text-neutral-900">No matching market finds yet</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-neutral-500">
                            Try a different keyword or category to discover more approved listings.
                        </p>
                        @if ($searchTerm !== '' || $selectedCategory !== null)
                            <a href="{{ route('shop.home') }}" class="brand-button-primary mt-5">
                                Clear filters
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        </section>
    </div>
</x-layouts::app>

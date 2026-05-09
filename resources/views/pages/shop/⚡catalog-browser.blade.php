<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'category', except: '')]
    public string $selectedCategory = '';

    #[Url(as: 'max_price', except: '')]
    public string $maxPrice = '';

    #[Url(except: '')]
    public string $sort = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedMaxPrice(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->selectedCategory = '';
        $this->maxPrice = '';
        $this->sort = '';

        $this->resetPage();
    }

    #[Computed]
    public function categories(): Collection
    {
        $visibleProducts = fn (Builder $query): Builder => $query
            ->active()
            ->whereHas('vendor', fn (Builder $builder): Builder => $builder->approved());

        $visibleChildCategories = function ($query) use ($visibleProducts): void {
            $query->whereHas('products', $visibleProducts);
        };

        return Category::query()
            ->parents()
            ->whereHas('children', $visibleChildCategories)
            ->with(['children' => function ($query) use ($visibleChildCategories): void {
                $visibleChildCategories($query);

                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedCategoryModel(): ?Category
    {
        $selectedCategoryId = $this->positiveIntegerFrom($this->selectedCategory);

        if ($selectedCategoryId === null) {
            return null;
        }

        return Category::query()
            ->with([
                'children:id,parent_id',
                'parent:id,name',
            ])
            ->find($selectedCategoryId);
    }

    #[Computed]
    public function selectedCategoryName(): ?string
    {
        return $this->selectedCategoryModel?->name;
    }

    #[Computed]
    public function filterSummary(): string
    {
        return match (true) {
            $this->search !== '' && $this->selectedCategoryModel !== null => 'You are narrowing the market by keyword and category for a faster shortlist.',
            $this->search !== '' => 'Keyword search is spotlighting listings that match what you are craving today.',
            $this->selectedCategoryModel !== null => 'You are browsing one market aisle at a time for a cleaner, easier scan.',
            default => 'All approved stalls and live listings are open for browsing right now.',
        };
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->selectedCategory !== ''
            || $this->maxPrice !== ''
            || $this->sort !== '';
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $selectedCategory = $this->selectedCategoryModel;

        return Product::query()
            ->visibleToCustomers()
            ->when(
                $selectedCategory !== null,
                fn (Builder $query): Builder => $query->whereIn('category_id', $this->selectedCategoryFilterIds($selectedCategory)),
            )
            ->search($this->search)
            ->withinMaxPrice($this->positiveIntegerFrom($this->maxPrice))
            ->sortForStorefront($this->sort)
            ->paginate(12);
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }

    /**
     * @return array<int, int>
     */
    private function selectedCategoryFilterIds(Category $selectedCategory): array
    {
        return $selectedCategory->children
            ->pluck('id')
            ->prepend($selectedCategory->getKey())
            ->unique()
            ->values()
            ->all();
    }

    private function positiveIntegerFrom(?string $value): ?int
    {
        $parsedValue = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $parsedValue === false ? null : $parsedValue;
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="grid items-start gap-8 xl:grid-cols-[20rem_minmax(0,1fr)] 2xl:grid-cols-[22rem_minmax(0,1fr)]">
        <aside
            x-data="{ filtersOpen: false, isDesktop: window.innerWidth >= 1280 }"
            x-on:resize.window="isDesktop = window.innerWidth >= 1280; if (isDesktop) { filtersOpen = false; }"
            class="self-start scrollbar-none xl:sticky xl:top-[76px]"
        >
            <button
                type="button"
                x-on:click="filtersOpen = !filtersOpen"
                class="brand-button-secondary mb-3 w-full xl:hidden"
            >
                <i class="fa-solid fa-sliders text-xs"></i>
                <span x-text="filtersOpen ? @js(__('Hide filters')) : @js(__('Show filters'))">{{ __('Show filters') }}</span>
            </button>

            <div
                x-cloak
                x-show="isDesktop || filtersOpen"
                x-transition
                class="brand-panel flex flex-col gap-5 p-5"
            >
                <div class="space-y-5">
                    <div>
                    <label for="storefront-search" class="mb-2 block text-xs font-semibold uppercase tracking-[0.14em] text-stone-500 dark:text-zinc-400">
                        {{ __('Search') }}
                    </label>
                    <input
                        id="storefront-search"
                        type="search"
                        wire:model.live.debounce.250ms="search"
                        placeholder="{{ __('Try ampalaya or seafood') }}"
                        autocomplete="off"
                        class="brand-input"
                    >
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Category') }}</flux:label>
                        <flux:select wire:model.change.live="selectedCategory" placeholder="{{ __('All categories') }}">
                            <flux:select.option value="" :label="__('All categories')" />
                            @foreach ($this->categories as $category)
                                <optgroup label="{{ $category->name }}" wire:key="catalog-category-group-{{ $category->id }}">
                                    <flux:select.option :value="$category->id" :label="__('All :category', ['category' => $category->name])" />
                                    @foreach ($category->children as $childCategory)
                                        <flux:select.option :value="$childCategory->id" :label="$childCategory->name" wire:key="catalog-category-option-{{ $childCategory->id }}" />
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Max price (:currency)', ['currency' => '₱']) }}</flux:label>
                        <flux:select wire:model.change.live="maxPrice" placeholder="{{ __('Any price') }}">
                            <flux:select.option value="" :label="__('Any price')" />
                            <flux:select.option value="50" :label="__('Under ₱50')" />
                            <flux:select.option value="100" :label="__('Under ₱100')" />
                            <flux:select.option value="200" :label="__('Under ₱200')" />
                            <flux:select.option value="500" :label="__('Under ₱500')" />
                            <flux:select.option value="1000" :label="__('Under ₱1,000')" />
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Sort by') }}</flux:label>
                        <flux:select wire:model.change.live="sort" placeholder="{{ __('Recently added') }}">
                            <flux:select.option value="" :label="__('Recently added')" />
                            <flux:select.option value="price_asc" :label="__('Price: low to high')" />
                            <flux:select.option value="price_desc" :label="__('Price: high to low')" />
                            <flux:select.option value="name_asc" :label="__('Name: A-Z')" />
                        </flux:select>
                    </flux:field>

                    @if ($this->hasActiveFilters)
                        <div class="pt-1">
                            <button
                                type="button"
                                wire:click="clearFilters"
                                wire:loading.attr="disabled"
                                wire:target="clearFilters"
                                class="brand-button-secondary w-full"
                            >
                                {{ __('Clear filters') }}
                            </button>
                        </div>
                    @endif
                </div>

                <div class="mt-auto border-t border-stone-200 pt-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-stone-500 dark:text-zinc-400">
                        {{ $this->products->total() }} {{ Str::plural('product', $this->products->total()) }}
                        {{ $this->selectedCategoryName ? 'in '.$this->selectedCategoryName : 'available' }}
                    </p>
                    <p class="sr-only" aria-live="polite">{{ $this->filterSummary }}</p>
                </div>
            </div>
        </aside>

        <section id="storefront-results">
            <div
                class="transition duration-200"
                wire:loading.class="opacity-60"
                wire:target="search,selectedCategory,maxPrice,sort,clearFilters,gotoPage,previousPage,nextPage"
            >
                <p
                    class="mb-4 hidden items-center gap-2 text-xs font-medium text-stone-500 dark:text-zinc-400"
                    wire:loading.flex
                    wire:target="search,selectedCategory,maxPrice,sort,clearFilters,gotoPage,previousPage,nextPage"
                >
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                    Updating listings...
                </p>

                @if ($this->products->isNotEmpty())
                    <div class="grid gap-6 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($this->products as $product)
                            <article wire:key="catalog-product-{{ $product->id }}" class="group flex h-full flex-col overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-zinc-900">
                                <a href="{{ route('shop.products.show', $product) }}" class="block">
                                    <div class="relative aspect-[5/4] overflow-hidden bg-stone-100 dark:bg-zinc-800">
                                        <img
                                            src="{{ $product->image }}"
                                            alt="{{ $product->name }}"
                                            onerror="this.src='https://placehold.co/640x640/e7e5e4/9ca3af?text=No+Image'"
                                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                            loading="lazy"
                                        >

                                        <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4">
                                            <span class="rounded-full bg-white/92 px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm dark:bg-zinc-900/90 dark:text-zinc-100">
                                                {{ $product->category->name }}
                                            </span>

                                            @if ($product->stock_quantity > 0)
                                                <span class="brand-accent-pill rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm">
                                                    {{ $product->unitLabel() }}
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
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="brand-accent-text text-xs font-semibold uppercase tracking-[0.18em]">{{ $product->vendor->store_name }}</p>
                                            <a
                                                href="{{ route('shop.vendors.show', $product->vendor) }}"
                                                class="brand-hover-text mt-1 inline-flex items-center gap-2 text-xs font-medium text-neutral-500 transition dark:text-zinc-400"
                                            >
                                                Visit stall
                                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                            </a>
                                        </div>
                                        <span class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->priceWithUnit() }}</span>
                                    </div>

                                    <a href="{{ route('shop.products.show', $product) }}" class="brand-group-hover-text mt-3 block text-2xl font-semibold text-neutral-900 transition dark:text-zinc-100">
                                        {{ $product->name }}
                                    </a>

                                    <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
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

                    @if ($this->products->hasPages())
                        <div class="mt-8">
                            {{ $this->products->onEachSide(1)->links(data: ['scrollTo' => '#storefront-results']) }}
                        </div>
                    @endif
                @else
                    <div class="brand-panel px-6 py-14 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                            <i class="fa-solid fa-magnifying-glass text-xl"></i>
                        </span>
                        <h3 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">No matching market finds yet</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                            Try a different keyword or category to discover more approved listings.
                        </p>
                        @if ($this->hasActiveFilters)
                            <button type="button" wire:click="clearFilters" class="brand-button-primary mt-5">
                                <span class="text-accent-foreground">Clear filters</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    </section>
</div>

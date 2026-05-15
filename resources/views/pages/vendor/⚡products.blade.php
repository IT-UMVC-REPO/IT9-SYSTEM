<?php

use App\Concerns\HasVendorGuard;
use App\Enums\AuditEvent;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\StockManager;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My products')] class extends Component {
    use HasVendorGuard;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';

    public ?int $restockProductId = null;

    public string $restockQuantity = '';

    public bool $showRestockModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedShowRestockModal(bool $showRestockModal): void
    {
        if ($showRestockModal) {
            return;
        }

        $this->restockProductId = null;
        $this->restockQuantity = '';
        $this->resetValidation(['restockQuantity']);
    }

    public function toggleStatus(int $productId): void
    {
        $product = Product::query()
            ->forVendor($this->approvedVendorProfile()->getKey())
            ->findOrFail($productId);

        $product->forceFill([
            'status' => $product->status === ProductStatus::Active
                ? ProductStatus::Inactive
                : ProductStatus::Active,
        ])->save();

        Flux::toast(variant: 'success', text: __('Listing updated.'));
    }

    public function deleteProduct(int $productId): void
    {
        $product = Product::query()
            ->forVendor($this->approvedVendorProfile()->getKey())
            ->findOrFail($productId);

        $storedImagePath = $product->getRawOriginal('image');

        if (
            filled($storedImagePath)
            && ! Str::startsWith($storedImagePath, ['http://', 'https://', '//'])
            && Storage::disk('public')->exists($storedImagePath)
        ) {
            Storage::disk('public')->delete($storedImagePath);
        }

        $product->delete();

        AuditLogger::log(AuditEvent::ProductDeleted, "Vendor deleted product '{$product->name}' (ID:{$product->id}).", null, auth()->id());

        Flux::toast(variant: 'success', text: __('Listing deleted.'));
    }

    public function openRestock(int $productId): void
    {
        $this->restockProductId = $productId;
        $this->restockQuantity = '';
        $this->showRestockModal = true;
        $this->resetValidation(['restockQuantity']);
    }

    public function restock(): void
    {
        $this->validate([
            'restockQuantity' => ['required', 'integer', 'min:1', 'max:99999'],
        ]);

        $product = Product::query()
            ->with('defaultVariant')
            ->forVendor($this->approvedVendorProfile()->getKey())
            ->findOrFail($this->restockProductId);

        if ($product->defaultVariant !== null) {
            app(StockManager::class)->incrementStock($product->defaultVariant, (int) $this->restockQuantity);
        } else {
            app(StockManager::class)->incrementProductStock($product, (int) $this->restockQuantity);
        }

        $product->refresh();

        AuditLogger::log(AuditEvent::ProductRestocked, "Vendor restocked '{$product->name}' by {$this->restockQuantity} {$product->unit->abbreviation()}. New total: {$product->unitLabel()}.", $product);

        Flux::toast(
            variant: 'success',
            text: __('Stock updated. :product now has :stock.', [
                'product' => $product->name,
                'stock' => $product->unitLabel(),
            ]),
        );

        $this->restockProductId = null;
        $this->restockQuantity = '';
        $this->showRestockModal = false;

        unset($this->products);
        unset($this->stats);
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $vendorProfile = $this->approvedVendorProfile();

        return Product::query()
            ->forVendor($vendorProfile->getKey())
            ->with(['category:id,name', 'vendor:id,user_id'])
            ->when(
                $this->search !== '',
                fn ($query) => $query->search($this->search),
            )
            ->when(
                $this->statusFilter !== '',
                fn ($query) => $query->where('status', $this->statusFilter),
            )
            ->when(
                $this->categoryFilter !== '',
                fn ($query) => $query->where('category_id', (int) $this->categoryFilter),
            )
            ->latest()
            ->paginate(12);
    }

    #[Computed]
    public function categoryOptions(): Collection
    {
        return Category::query()
            ->leaves()
            ->whereHas('products', fn ($query) => $query->forVendor($this->approvedVendorProfile()->getKey()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function stats(): array
    {
        $vendorId = $this->approvedVendorProfile()->getKey();
        $baseQuery = Product::query()->forVendor($vendorId);

        return [
            [
                'label' => __('Total listings'),
                'value' => number_format((clone $baseQuery)->count()),
                'dot_color' => null,
            ],
            [
                'label' => __('Active'),
                'value' => number_format((clone $baseQuery)->where('status', ProductStatus::Active)->count()),
                'dot_color' => 'bg-emerald-500',
            ],
            [
                'label' => __('Draft'),
                'value' => number_format((clone $baseQuery)->where('status', ProductStatus::Inactive)->count()),
                'dot_color' => 'bg-stone-400',
            ],
            [
                'label' => __('Out of stock'),
                'value' => number_format((clone $baseQuery)->where('stock_quantity', 0)->count()),
                'dot_color' => 'bg-rose-500',
            ],
            [
                'label' => __('Low stock'),
                'value' => number_format((clone $baseQuery)->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count()),
                'dot_color' => 'bg-amber-500',
            ],
            [
                'label' => __('Total units'),
                'value' => number_format((int) (clone $baseQuery)->sum('stock_quantity')),
                'dot_color' => null,
            ],
        ];
    }

    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }

}; ?>

<div>
<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('vendor.dashboard') }}" wire:navigate class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                {{ __('Return to Dashboard') }}
            </a>
            <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ __('Manage your product listings') }}
            </h1>
            <p class="mt-3 max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Update pricing, keep stock visible, and decide which listings shoppers can browse in your storefront.') }}
            </p>
        </div>

        <a href="{{ route('vendor.products.create') }}" wire:navigate class="brand-button-primary active:scale-[0.96]">
            <i class="fa-solid fa-plus text-xs"></i>
            {{ __('New product') }}
        </a>
    </section>

    <x-vendor-management-tabs />

    <section class="brand-panel p-6">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-stone-200 pb-5 dark:border-white/10">
            @foreach ($this->stats as $stat)
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold tabular-nums text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</span>
                    <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ $stat['label'] }}</span>
                    @if ($stat['dot_color'] ?? null)
                        <span class="ml-1 inline-block h-2 w-2 rounded-full {{ $stat['dot_color'] }}"></span>
                    @endif
                </div>
                @if (! $loop->last)
                    <div class="h-6 w-px bg-stone-200 dark:bg-white/10"></div>
                @endif
            @endforeach
        </div>
    </section>

    <section class="brand-panel p-6">
        <div class="grid gap-4 lg:grid-cols-3">
            <flux:input wire:model.live.debounce.250ms="search" :label="__('Search listings')" type="search" :placeholder="__('Search by product name')" />

            <flux:select wire:model.live="statusFilter" :label="__('Status')" placeholder="{{ __('All statuses') }}">
                <flux:select.option value="" :label="__('All statuses')" />
                <flux:select.option :value="ProductStatus::Active->value" :label="__('Active')" />
                <flux:select.option :value="ProductStatus::Inactive->value" :label="__('Inactive')" />
            </flux:select>

            <flux:select wire:model.live="categoryFilter" :label="__('Category')" placeholder="{{ __('All categories') }}">
                <flux:select.option value="" :label="__('All categories')" />
                @foreach ($this->categoryOptions as $category)
                    <flux:select.option :value="$category->id" :label="$category->name" />
                @endforeach
            </flux:select>
        </div>
    </section>

    <section
        class="transition duration-200"
        wire:loading.class="opacity-60 blur-[0.5px]"
                wire:target="search,statusFilter,categoryFilter,toggleStatus,deleteProduct,restock,gotoPage,previousPage,nextPage"
    >
        @if ($this->products->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->products as $product)
                    <article wire:key="vendor-product-{{ $product->id }}" wire:transition class="brand-panel flex h-full flex-col p-5 sm:p-6">
                        <div class="overflow-hidden rounded-[1.5rem] bg-stone-100 dark:bg-zinc-800">
                            <img
                                src="{{ $product->image_url }}"
                                alt="{{ $product->name }}"
                                class="aspect-[4/3] w-full object-cover"
                                loading="lazy"
                            >
                        </div>

                        <div class="mt-5 flex flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="brand-badge">{{ $product->category->name }}</span>
                                    <h2 class="mt-3 text-xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->name }}</h2>
                                </div>

                                <span class="text-lg font-semibold text-neutral-900 dark:text-zinc-100">
                                    {{ $product->priceWithUnit() }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                                <p @class([
                                    'font-semibold',
                                    'text-rose-600 dark:text-rose-300' => $product->stock_quantity === 0,
                                    'text-amber-600 dark:text-amber-300' => $product->stock_quantity > 0 && $product->stock_quantity <= 10,
                                    'text-emerald-600 dark:text-emerald-300' => $product->stock_quantity > 10,
                                ])>
                                    {{ __(':stock in stock', ['stock' => $product->unitLabel()]) }}
                                </p>

                                <span @class([
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $product->status === ProductStatus::Active,
                                    'bg-stone-100 text-stone-700 dark:bg-zinc-800 dark:text-zinc-300' => $product->status === ProductStatus::Inactive,
                                ])>
                                    {{ $product->status->value }}
                                </span>
                            </div>

                            <div class="mt-auto grid gap-3 pt-6">
                                <button
                                    type="button"
                                    wire:click="toggleStatus({{ $product->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleStatus({{ $product->id }})"
                                    class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]"
                                >
                                    {{ $product->status === ProductStatus::Active ? __('Set inactive') : __('Publish listing') }}
                                </button>

                                <div class="grid grid-cols-3 gap-2">
                                    <a href="{{ route('vendor.products.edit', $product) }}" wire:navigate class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96]">
                                        {{ __('Edit') }}
                                    </a>

                                    <button
                                        type="button"
                                        wire:click="openRestock({{ $product->id }})"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-[var(--brand-200)] bg-[var(--brand-50)] px-3 py-3 text-sm font-semibold text-[var(--brand-700)] transition-all duration-150 hover:bg-[var(--brand-100)] active:scale-[0.97] dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-300)]"
                                    >
                                        {{ __('Restock') }}
                                    </button>

                                    <button
                                        type="button"
                                        x-data
                                        x-on:click="$flux.modal('delete-vendor-product-{{ $product->id }}').show()"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 px-5 py-3 text-sm font-semibold text-rose-600 transition-all duration-150 hover:bg-rose-50 active:scale-[0.97] dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </div>

                                <flux:modal name="delete-vendor-product-{{ $product->id }}" class="max-w-sm">
                                    <div class="p-6 space-y-4">
                                        <flux:heading size="lg">{{ __('Delete listing?') }}</flux:heading>
                                        <flux:text>{{ __('This listing and its image will be permanently removed.') }}</flux:text>

                                        <div class="flex justify-end gap-3 pt-2">
                                            <flux:button variant="ghost" x-on:click="$flux.modal('delete-vendor-product-{{ $product->id }}').close()">
                                                {{ __('Cancel') }}
                                            </flux:button>

                                            <flux:button variant="danger" wire:click="deleteProduct({{ $product->id }})">
                                                {{ __('Delete listing') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($this->products->hasPages())
                <div class="mt-8">
                    {{ $this->products->onEachSide(1)->links() }}
                </div>
            @endif
        @else
            <div wire:transition class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-box-open text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('No listings to manage yet') }}
                </h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Create your first product to start building out your storefront and give shoppers something to browse.') }}
                </p>
                <a href="{{ route('vendor.products.create') }}" wire:navigate class="brand-button-primary active:scale-[0.96] mt-6">
                    {{ __('Create a product') }}
                </a>
            </div>
        @endif
    </section>
</div>

<flux:modal
    wire:model.self="showRestockModal"
    name="restock-product-modal"
    class="max-w-sm"
>
    @if ($restockProductId !== null)
        @php($restockProduct = $this->products->getCollection()->firstWhere('id', $restockProductId))

        <div class="animate-in p-6 space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Restock product') }}</flux:heading>
                <flux:text class="mt-2">
                    @if ($restockProduct)
                        {{ __('Add to your current :stock for ":name".', [
                            'stock' => $restockProduct->unitLabel(),
                            'name' => $restockProduct->name,
                        ]) }}
                    @endif
                </flux:text>
            </div>

            <div>
                <flux:field>
                    <flux:label>
                        {{ __('How many :units are you adding?', [
                            'units' => $restockProduct?->unit->label() ?? __('units'),
                        ]) }}
                    </flux:label>
                    <flux:input
                        wire:model="restockQuantity"
                        type="number"
                        min="1"
                        :placeholder="__('e.g. 50')"
                        autofocus
                    />
                    <flux:error name="restockQuantity" />
                </flux:field>

                @if ($restockProduct && filled($restockQuantity) && is_numeric($restockQuantity) && (int) $restockQuantity > 0)
                    <p class="mt-3 text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                        {{ __('New total: :total', [
                            'total' => \App\Support\UnitFormatter::format($restockProduct->unit, $restockProduct->stock_quantity + (int) $restockQuantity),
                        ]) }}
                    </p>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button
                    variant="ghost"
                    wire:click="$set('showRestockModal', false)"
                >
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button
                    variant="primary"
                    wire:click="restock"
                    wire:loading.attr="disabled"
                    wire:target="restock"
                >
                    <span wire:loading.remove wire:target="restock">{{ __('Add stock') }}</span>
                    <span wire:loading wire:target="restock">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        </div>
    @endif
</flux:modal>
</div>

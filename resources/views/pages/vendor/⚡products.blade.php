<?php

use App\Concerns\HasVendorGuard;
use App\Enums\AuditEvent;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
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
            ->forVendor($this->approvedVendorProfile()->getKey())
            ->findOrFail($this->restockProductId);

        $product->increment('stock_quantity', (int) $this->restockQuantity);
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

        return [
            'total' => Product::query()->forVendor($vendorId)->count(),
            'active' => Product::query()->forVendor($vendorId)->where('status', ProductStatus::Active)->count(),
            'inactive' => Product::query()->forVendor($vendorId)->where('status', ProductStatus::Inactive)->count(),
            'out_of_stock' => Product::query()->forVendor($vendorId)->where('stock_quantity', 0)->count(),
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
            <span class="brand-kicker">{{ __('Vendor catalog') }}</span>
            <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ __('Manage your product listings') }}
            </h1>
            <p class="mt-3 max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('Update pricing, keep stock visible, and decide which listings shoppers can browse in your storefront.') }}
            </p>
        </div>

        <a href="{{ route('vendor.products.create') }}" wire:navigate class="brand-button-primary">
            <i class="fa-solid fa-plus text-xs"></i>
            {{ __('New product') }}
        </a>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => __('Total products'), 'value' => $this->stats['total']],
            ['label' => __('Active products'), 'value' => $this->stats['active']],
            ['label' => __('Inactive products'), 'value' => $this->stats['inactive']],
            ['label' => __('Out of stock'), 'value' => $this->stats['out_of_stock']],
        ] as $stat)
            <div class="brand-panel-muted p-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                    {{ $stat['label'] }}
                </p>
                <p class="mt-3 text-3xl font-semibold text-neutral-900 dark:text-zinc-100">{{ $stat['value'] }}</p>
            </div>
        @endforeach
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
        wire:loading.class="opacity-60"
                wire:target="search,statusFilter,categoryFilter,toggleStatus,deleteProduct,restock,gotoPage,previousPage,nextPage"
    >
        @if ($this->products->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->products as $product)
                    <article wire:key="vendor-product-{{ $product->id }}" class="brand-panel flex h-full flex-col p-5 sm:p-6">
                        <div class="overflow-hidden rounded-[1.5rem] bg-stone-100 dark:bg-zinc-800">
                            <img
                                src="{{ $product->image_url }}"
                                alt="{{ $product->name }}"
                                class="aspect-[4/3] w-full object-cover"
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
                                    class="brand-button-secondary w-full"
                                >
                                    {{ $product->status === ProductStatus::Active ? __('Set inactive') : __('Publish listing') }}
                                </button>

                                <div class="grid grid-cols-3 gap-2">
                                    <a href="{{ route('vendor.products.edit', $product) }}" wire:navigate class="brand-button-secondary w-full">
                                        {{ __('Edit') }}
                                    </a>

                                    <button
                                        type="button"
                                        wire:click="openRestock({{ $product->id }})"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-[var(--brand-200)] bg-[var(--brand-50)] px-3 py-3 text-sm font-semibold text-[var(--brand-700)] transition hover:bg-[var(--brand-100)] dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-300)]"
                                    >
                                        {{ __('Restock') }}
                                    </button>

                                    <button
                                        type="button"
                                        x-data
                                        x-on:click="$flux.modal('delete-vendor-product-{{ $product->id }}').show()"
                                        class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 px-5 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10"
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
            <div class="brand-panel px-6 py-14 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-neutral-400 dark:bg-zinc-800 dark:text-zinc-400">
                    <i class="fa-solid fa-box-open text-xl"></i>
                </span>
                <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('No listings to manage yet') }}
                </h2>
                <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Create your first product to start building out your storefront and give shoppers something to browse.') }}
                </p>
                <a href="{{ route('vendor.products.create') }}" wire:navigate class="brand-button-primary mt-6">
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

        <div class="p-6 space-y-5">
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
                            'total' => $restockProduct->unit->stockLabel($restockProduct->stock_quantity + (int) $restockQuantity),
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

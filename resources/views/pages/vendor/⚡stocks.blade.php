<?php

use App\Concerns\HasPaginationView;
use App\Concerns\HasVendorGuard;
use App\Enums\AuditEvent;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\VendorProfile;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stock Management')] class extends Component {
    use HasPaginationView;
    use HasVendorGuard;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $statusFilter = 'all';

    #[Url(except: '')]
    public string $categoryFilter = '';

    #[Url(except: 'name_asc')]
    public string $sort = 'name_asc';

    /**
     * @var array<int, array{quantity: string, mode: string}>
     */
    public array $inlineEdits = [];

    /**
     * @var array<int, int|string>
     */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public string $bulkAction = '';

    public string $bulkQuantity = '';

    public bool $showBulkModal = false;

    public int $lowStockThreshold = 10;

    public bool $showThresholdEditor = false;

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

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selectedIds = $value
            ? $this->products->pluck('id')->map(fn (int $id): int => $id)->all()
            : [];
    }

    #[Computed]
    public function vendorProfile(): VendorProfile
    {
        return $this->approvedVendorProfile();
    }

    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return Product::query()
            ->where('vendor_id', $this->vendorProfile->getKey())
            ->with('category:id,name,parent_id,slug')
            ->when(filled($this->search), fn ($query) => $query->search($this->search))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('status', ProductStatus::Active))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('status', ProductStatus::Inactive))
            ->when(
                $this->statusFilter === 'low',
                fn ($query) => $query
                    ->where('status', ProductStatus::Active)
                    ->where('stock_quantity', '>', 0)
                    ->where('stock_quantity', '<=', $this->lowStockThreshold),
            )
            ->when($this->statusFilter === 'out', fn ($query) => $query->where('stock_quantity', 0))
            ->when(filled($this->categoryFilter), fn ($query) => $query->where('category_id', (int) $this->categoryFilter))
            ->when($this->sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->when($this->sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($this->sort === 'stock_asc', fn ($query) => $query->orderBy('stock_quantity')->orderBy('name'))
            ->when($this->sort === 'stock_desc', fn ($query) => $query->orderByDesc('stock_quantity')->orderBy('name'))
            ->when($this->sort === 'updated_asc', fn ($query) => $query->orderBy('updated_at'))
            ->when($this->sort === 'updated_desc', fn ($query) => $query->orderByDesc('updated_at'))
            ->paginate(25);
    }

    /**
     * @return array{total: int, active: int, inactive: int, out_of_stock: int, low_stock: int, total_units: int}
     */
    #[Computed]
    public function stockSummary(): array
    {
        $base = Product::query()->where('vendor_id', $this->vendorProfile->getKey());

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', ProductStatus::Active)->count(),
            'inactive' => (clone $base)->where('status', ProductStatus::Inactive)->count(),
            'out_of_stock' => (clone $base)->where('stock_quantity', 0)->count(),
            'low_stock' => (clone $base)
                ->where('status', ProductStatus::Active)
                ->where('stock_quantity', '>', 0)
                ->where('stock_quantity', '<=', $this->lowStockThreshold)
                ->count(),
            'total_units' => (int) (clone $base)->sum('stock_quantity'),
        ];
    }

    #[Computed]
    public function categoryOptions(): Collection
    {
        return Product::query()
            ->where('vendor_id', $this->vendorProfile->getKey())
            ->with('category:id,name')
            ->get(['category_id'])
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function startInlineEdit(int $productId): void
    {
        $product = $this->findOwnedProduct($productId);

        $this->inlineEdits[$productId] = [
            'quantity' => (string) $product->stock_quantity,
            'mode' => 'set',
        ];
    }

    public function cancelInlineEdit(int $productId): void
    {
        unset($this->inlineEdits[$productId]);
    }

    public function saveInlineEdit(int $productId): void
    {
        $edit = $this->inlineEdits[$productId] ?? null;

        if ($edit === null) {
            return;
        }

        $this->validateOnly("inlineEdits.{$productId}.quantity", [
            "inlineEdits.{$productId}.quantity" => ['required', 'numeric', 'min:0', 'max:999999'],
        ]);

        $product = $this->findOwnedProduct($productId);
        $oldQty = $product->stock_quantity;
        $input = (int) round((float) $edit['quantity']);

        $newQty = match ($edit['mode']) {
            'add' => max(0, $oldQty + $input),
            'subtract' => max(0, $oldQty - $input),
            default => max(0, $input),
        };

        $product->update(['stock_quantity' => $newQty]);

        unset($this->products, $this->stockSummary);

        $delta = $newQty - $oldQty;
        $sign = $delta >= 0 ? '+' : '';

        AuditLogger::log(
            AuditEvent::ProductRestocked,
            "Vendor updated stock of '{$product->name}': {$oldQty} → {$newQty} ({$sign}{$delta}) via inline edit.",
            $product,
        );

        unset($this->inlineEdits[$productId]);

        Flux::toast(variant: 'success', text: __('Stock updated for :name.', ['name' => $product->name]));
    }

    public function quickAdjust(int $productId, string $direction): void
    {
        $product = $this->findOwnedProduct($productId);
        $oldQty = $product->stock_quantity;
        $newQty = $direction === 'up' ? $oldQty + 1 : max(0, $oldQty - 1);

        $product->update(['stock_quantity' => $newQty]);

        unset($this->products, $this->stockSummary);
    }

    public function setStock(int $productId, int $quantity): void
    {
        $product = $this->findOwnedProduct($productId);
        $oldQty = $product->stock_quantity;
        $newQty = max(0, $quantity);

        $product->update(['stock_quantity' => $newQty]);

        unset($this->products, $this->stockSummary);

        AuditLogger::log(
            AuditEvent::ProductRestocked,
            "Vendor set stock of '{$product->name}': {$oldQty} → {$newQty}.",
            $product,
        );

        Flux::toast(variant: 'success', text: __('Stock updated for :name.', ['name' => $product->name]));
    }

    public function toggleStatus(int $productId): void
    {
        $product = $this->findOwnedProduct($productId);
        $newStatus = $product->status === ProductStatus::Active
            ? ProductStatus::Inactive
            : ProductStatus::Active;

        $product->update(['status' => $newStatus]);

        unset($this->products, $this->stockSummary);

        AuditLogger::log(
            $newStatus === ProductStatus::Active ? AuditEvent::ProductActivated : AuditEvent::ProductUpdated,
            "Vendor toggled status of '{$product->name}' to {$newStatus->value}.",
            $product,
        );

        Flux::toast(
            variant: $newStatus === ProductStatus::Active ? 'success' : 'warning',
            text: __(':name is now :status.', [
                'name' => $product->name,
                'status' => $newStatus === ProductStatus::Active ? __('active') : __('inactive'),
            ]),
        );
    }

    public function openBulkModal(string $action): void
    {
        if (empty($this->selectedIds)) {
            Flux::toast(variant: 'warning', text: __('Select at least one product first.'));

            return;
        }

        $this->bulkAction = $action;
        $this->bulkQuantity = '';
        $this->showBulkModal = true;
    }

    public function executeBulkAction(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $needsQty = in_array($this->bulkAction, ['add', 'subtract', 'set'], true);

        if ($needsQty) {
            $this->validate([
                'bulkQuantity' => ['required', 'numeric', 'min:0', 'max:999999'],
            ]);
        }

        $products = Product::query()
            ->where('vendor_id', $this->vendorProfile->getKey())
            ->whereIn('id', $this->selectedIds)
            ->get();

        $qty = $needsQty ? (int) round((float) $this->bulkQuantity) : 0;

        foreach ($products as $product) {
            $oldQty = $product->stock_quantity;

            match ($this->bulkAction) {
                'add' => $product->update(['stock_quantity' => $oldQty + $qty]),
                'subtract' => $product->update(['stock_quantity' => max(0, $oldQty - $qty)]),
                'set' => $product->update(['stock_quantity' => max(0, $qty)]),
                'activate' => $product->update(['status' => ProductStatus::Active]),
                'deactivate' => $product->update(['status' => ProductStatus::Inactive]),
                default => null,
            };

            $product->refresh();

            AuditLogger::log(
                in_array($this->bulkAction, ['activate', 'deactivate'], true) ? AuditEvent::ProductUpdated : AuditEvent::ProductRestocked,
                "Bulk action '{$this->bulkAction}' on '{$product->name}': {$oldQty} → {$product->stock_quantity}.",
                $product,
            );
        }

        $this->showBulkModal = false;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->bulkAction = '';
        $this->bulkQuantity = '';

        unset($this->products, $this->stockSummary);

        Flux::toast(
            variant: 'success',
            text: __('Bulk action applied to :count products.', ['count' => $products->count()]),
        );
    }

    public function updateThreshold(): void
    {
        $this->validate(['lowStockThreshold' => ['required', 'integer', 'min:1', 'max:9999']]);

        $this->showThresholdEditor = false;

        unset($this->stockSummary, $this->products);

        Flux::toast(variant: 'success', text: __('Low stock threshold updated to :n units.', ['n' => $this->lowStockThreshold]));
    }

    private function findOwnedProduct(int $productId): Product
    {
        $product = Product::query()->findOrFail($productId);

        abort_if($product->vendor_id !== $this->vendorProfile->getKey(), 403);

        return $product;
    }

};
?>

<div class="mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:px-8 space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <x-page-heading
            kicker="{{ __('Products') }}"
            :title="__('Stock Management')"
            :description="__('Monitor, adjust, and bulk-update stock levels across all your listings from one place.')"
            icon="fa-solid fa-boxes-stacked"
        />

        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <flux:button variant="ghost" icon="pencil-square" :href="route('vendor.products')" wire:navigate>
                {{ __('Manage products') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" :href="route('vendor.products.create')" wire:navigate>
                {{ __('New product') }}
            </flux:button>
        </div>
    </div>

    <div class="flex items-center gap-1 rounded-2xl border border-stone-200 bg-stone-100 p-1 dark:border-white/10 dark:bg-zinc-800/60">
        <a href="{{ route('vendor.products') }}" wire:navigate
            @class([
                'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
                'bg-white text-neutral-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => request()->routeIs('vendor.products'),
                'text-neutral-500 hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100' => ! request()->routeIs('vendor.products'),
            ])>
            <i class="fa-solid fa-boxes-stacked text-xs"></i>
            {{ __('Products') }}
        </a>
        <a href="{{ route('vendor.stocks') }}" wire:navigate
            @class([
                'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
                'bg-white text-neutral-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => request()->routeIs('vendor.stocks'),
                'text-neutral-500 hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100' => ! request()->routeIs('vendor.stocks'),
            ])>
            <i class="fa-solid fa-warehouse text-xs"></i>
            {{ __('Stock Manager') }}
        </a>
    </div>

    <section class="brand-panel p-6">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-stone-200 pb-5 dark:border-white/10">
        @foreach ([
            ['key' => 'all', 'label' => __('Total listings'), 'value' => $this->stockSummary['total'], 'clickable' => true],
            ['key' => 'active', 'label' => __('Active'), 'value' => $this->stockSummary['active'], 'clickable' => true],
            ['key' => 'inactive', 'label' => __('Inactive / Draft'), 'value' => $this->stockSummary['inactive'], 'clickable' => true],
            ['key' => 'out', 'label' => __('Out of stock'), 'value' => $this->stockSummary['out_of_stock'], 'clickable' => true],
            ['key' => 'low', 'label' => __('Low stock (<= :n)', ['n' => $lowStockThreshold]), 'value' => $this->stockSummary['low_stock'], 'clickable' => true],
            ['key' => null, 'label' => __('Total units'), 'value' => number_format($this->stockSummary['total_units']), 'clickable' => false],
        ] as $card)
            @if ($card['clickable'])
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $card['key'] }}')"
                    class="flex items-baseline gap-2 text-left transition hover:text-[var(--brand-700)] dark:hover:text-[var(--brand-300)]"
                >
                    <span class="text-2xl font-bold tabular-nums text-neutral-900 dark:text-zinc-100">{{ number_format($card['value']) }}</span>
                    <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ $card['label'] }}</span>
                    @if ($card['key'] === 'active')
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                    @elseif ($card['key'] === 'inactive')
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-stone-400"></span>
                    @elseif ($card['key'] === 'out')
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-rose-500"></span>
                    @elseif ($card['key'] === 'low')
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                    @endif
                </button>
            @else
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold tabular-nums text-neutral-900 dark:text-zinc-100">{{ $card['value'] }}</span>
                    <span class="text-sm font-medium text-neutral-500 dark:text-zinc-400">{{ $card['label'] }}</span>
                </div>
            @endif
            @if (! $loop->last)
                <div class="h-6 w-px bg-stone-200 dark:bg-white/10"></div>
            @endif
        @endforeach
        </div>
    </section>

    <div class="flex items-center justify-end gap-2 text-sm">
        <span class="text-neutral-500 dark:text-zinc-400">{{ __('Low stock threshold:') }}</span>
        @if (! $showThresholdEditor)
            <button
                type="button"
                wire:click="$set('showThresholdEditor', true)"
                class="font-semibold text-[var(--brand-700)] hover:underline dark:text-[var(--brand-400)]"
            >
                {{ $lowStockThreshold }} {{ __('units') }}
                <i class="fa-solid fa-pencil ml-1 text-xs"></i>
            </button>
        @else
            <form wire:submit="updateThreshold" class="flex items-center gap-2">
                <flux:input type="number" wire:model="lowStockThreshold" size="sm" min="1" max="9999" class="w-24" />
                <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" size="sm" variant="ghost" wire:click="$set('showThresholdEditor', false)">
                    {{ __('Cancel') }}
                </flux:button>
            </form>
        @endif
    </div>

    <section class="sticky top-16 z-20">
        <div class="brand-panel flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="min-w-64 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search products...')" icon="magnifying-glass" clearable />
            </div>

            <flux:select wire:model.live="categoryFilter" size="sm" class="min-w-48">
                <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                @foreach ($this->categoryOptions as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex flex-wrap gap-2">
                @foreach ([
                    'all' => __('All'),
                    'active' => __('Active'),
                    'inactive' => __('Inactive'),
                    'low' => __('Low stock'),
                    'out' => __('Out of stock'),
                ] as $filter => $label)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', '{{ $filter }}')"
                        @class([
                            'rounded-full border px-3 py-1 text-xs font-bold transition',
                            'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' => $statusFilter === $filter,
                            'border-stone-200 bg-white text-neutral-500 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $statusFilter !== $filter,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3">
                <flux:select wire:model.live="sort" size="sm">
                    <flux:select.option value="name_asc">{{ __('Name A-Z') }}</flux:select.option>
                    <flux:select.option value="name_desc">{{ __('Name Z-A') }}</flux:select.option>
                    <flux:select.option value="stock_asc">{{ __('Stock ↑') }}</flux:select.option>
                    <flux:select.option value="stock_desc">{{ __('Stock ↓') }}</flux:select.option>
                    <flux:select.option value="updated_desc">{{ __('Recently updated') }}</flux:select.option>
                    <flux:select.option value="updated_asc">{{ __('Oldest updated') }}</flux:select.option>
                </flux:select>

                <span class="text-sm text-neutral-500 dark:text-zinc-400">
                    {{ $this->products->total() }} {{ Str::plural(__('product'), $this->products->total()) }}
                </span>
            </div>
        </div>
    </section>

    @if (count($selectedIds) > 0)
        <div
            x-data
            x-show="true"
            x-transition
            class="brand-panel flex flex-wrap items-center gap-3 border-2 border-[var(--brand-500)] px-4 py-3"
        >
            <span class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                {{ count($selectedIds) }} {{ Str::plural(__('product'), count($selectedIds)) }} {{ __('selected') }}
            </span>
            <div class="ml-auto flex flex-wrap gap-2">
                <flux:button size="sm" wire:click="openBulkModal('add')" icon="plus">{{ __('Add stock') }}</flux:button>
                <flux:button size="sm" wire:click="openBulkModal('subtract')" icon="minus">{{ __('Remove stock') }}</flux:button>
                <flux:button size="sm" wire:click="openBulkModal('set')" icon="pencil-square">{{ __('Set stock') }}</flux:button>
                <flux:button size="sm" wire:click="openBulkModal('activate')" variant="filled" class="bg-emerald-600 text-white hover:bg-emerald-500">{{ __('Activate') }}</flux:button>
                <flux:button size="sm" wire:click="openBulkModal('deactivate')" variant="ghost">{{ __('Deactivate') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="$set('selectedIds', []); $set('selectAll', false)">{{ __('Clear') }}</flux:button>
            </div>
        </div>
    @endif

    <section class="brand-panel overflow-hidden">
        @if ($this->products->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-left dark:divide-white/10">
                    <thead class="bg-stone-50 text-xs font-bold uppercase tracking-[0.16em] text-neutral-400 dark:bg-zinc-900 dark:text-zinc-500">
                        <tr>
                            <th class="w-12 px-4 py-4">
                                <flux:checkbox wire:model.live="selectAll" />
                            </th>
                            <th class="px-4 py-4">{{ __('Product') }}</th>
                            <th class="px-4 py-4">{{ __('Category') }}</th>
                            <th class="px-4 py-4">{{ __('Unit') }}</th>
                            <th class="px-4 py-4">{{ __('Stock level') }}</th>
                            <th class="px-4 py-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-white/10">
                        @foreach ($this->products as $product)
                            @php
                                $isOut = $product->stock_quantity === 0;
                                $isLow = $product->stock_quantity > 0 && $product->stock_quantity <= $lowStockThreshold;
                                $stockTone = $isOut ? 'rose' : ($isLow ? 'amber' : 'emerald');
                                $barWidth = min(100, max(4, (int) round(($product->stock_quantity / 200) * 100)));
                                $categoryName = $product->category?->parent
                                    ? $product->category->parent->name.' › '.$product->category->name
                                    : $product->category?->name;
                            @endphp

                            <tr wire:key="stock-row-{{ $product->id }}" class="group transition hover:bg-stone-50/80 dark:hover:bg-white/5">
                                <td class="px-4 py-5 align-top">
                                    <flux:checkbox wire:model.live="selectedIds" :value="$product->id" />
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-12 w-12 rounded-xl object-cover">
                                        <div class="min-w-0">
                                            <a href="{{ route('vendor.products.edit', $product) }}" wire:navigate class="block max-w-xs truncate font-semibold text-neutral-900 transition hover:text-[var(--brand-700)] dark:text-zinc-100">
                                                {{ $product->name }}
                                            </a>
                                            <span @class([
                                                'mt-1 inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.14em]',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $product->status === ProductStatus::Active,
                                                'bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-300' => $product->status === ProductStatus::Inactive,
                                            ])>
                                                {{ $product->status === ProductStatus::Active ? __('Active') : __('Draft') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-5 align-top text-sm text-neutral-500 dark:text-zinc-400">
                                    {{ $categoryName }}
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __($product->unit->label()) }}</p>
                                    <p class="text-xs text-neutral-400 dark:text-zinc-500">{{ __('per :unit', ['unit' => $product->unit->abbreviation()]) }}</p>
                                    @if ($product->convertedQuantityLabel(1))
                                        <span class="mt-2 inline-flex rounded-full bg-[var(--brand-50)] px-2.5 py-1 text-xs font-semibold text-[var(--brand-700)] dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-300)]">
                                            = {{ $product->convertedQuantityLabel(1) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-5 align-top">
                                    @if (isset($inlineEdits[$product->id]))
                                        <div class="flex min-w-[260px] flex-col gap-2">
                                            <div class="flex overflow-hidden rounded-lg border border-stone-200 text-xs font-semibold dark:border-zinc-700">
                                                @foreach (['set' => __('Set to'), 'add' => __('+ Add'), 'subtract' => __('- Remove')] as $mode => $label)
                                                    <button
                                                        type="button"
                                                        wire:click="$set('inlineEdits.{{ $product->id }}.mode', '{{ $mode }}')"
                                                        @class([
                                                            'flex-1 px-2 py-1.5 transition',
                                                            'bg-[var(--brand-600)] text-white' => $inlineEdits[$product->id]['mode'] === $mode,
                                                            'bg-white text-neutral-600 hover:bg-stone-50 dark:bg-zinc-800 dark:text-zinc-300' => $inlineEdits[$product->id]['mode'] !== $mode,
                                                        ])
                                                    >
                                                        {{ $label }}
                                                    </button>
                                                @endforeach
                                            </div>

                                            @php
                                                $edit = $inlineEdits[$product->id];
                                                $inputQty = (int) round((float) ($edit['quantity'] ?? 0));
                                                $preview = match ($edit['mode']) {
                                                    'add' => max(0, $product->stock_quantity + $inputQty),
                                                    'subtract' => max(0, $product->stock_quantity - $inputQty),
                                                    default => max(0, $inputQty),
                                                };
                                            @endphp
                                            <p class="text-xs text-neutral-400 dark:text-zinc-500">
                                                {{ __('Result: :current → :preview :unit', [
                                                    'current' => $product->stock_quantity,
                                                    'preview' => $preview,
                                                    'unit' => $product->unit->abbreviation(),
                                                ]) }}
                                            </p>

                                            <div class="flex items-center gap-2">
                                                <flux:input type="number" wire:model.live="inlineEdits.{{ $product->id }}.quantity" min="0" max="999999" size="sm" class="w-28 tabular-nums" placeholder="{{ $product->stock_quantity }}" />
                                                <flux:button size="sm" variant="primary" wire:click="saveInlineEdit({{ $product->id }})">{{ __('Save') }}</flux:button>
                                                <flux:button size="sm" variant="ghost" wire:click="cancelInlineEdit({{ $product->id }})">{{ __('Cancel') }}</flux:button>
                                            </div>

                                            @error("inlineEdits.{$product->id}.quantity")
                                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @else
                                        <div class="min-w-[240px] space-y-2">
                                            <div class="h-2 overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                                                <div @class([
                                                    'h-full rounded-full',
                                                    'bg-rose-500' => $stockTone === 'rose',
                                                    'bg-amber-500' => $stockTone === 'amber',
                                                    'bg-emerald-500' => $stockTone === 'emerald',
                                                ]) style="width: {{ $barWidth }}%;"></div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span @class([
                                                    'text-lg font-bold tabular-nums',
                                                    'text-rose-600 dark:text-rose-300' => $stockTone === 'rose',
                                                    'text-amber-600 dark:text-amber-300' => $stockTone === 'amber',
                                                    'text-emerald-600 dark:text-emerald-300' => $stockTone === 'emerald',
                                                ])>
                                                    {{ $product->unitLabel() }}
                                                </span>
                                                @if ($isOut)
                                                    <flux:badge color="rose" size="sm">{{ __('Out of stock') }}</flux:badge>
                                                @elseif ($isLow)
                                                    <flux:badge color="amber" size="sm">{{ __('Low stock') }}</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-5 align-top">
                                    <div class="flex justify-end gap-2 opacity-100 transition md:opacity-0 md:group-hover:opacity-100">
                                        <button type="button" wire:click="quickAdjust({{ $product->id }}, 'down')" class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 text-neutral-500 transition hover:bg-stone-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Decrease stock') }}">
                                            <i class="fa-solid fa-minus text-xs"></i>
                                        </button>
                                        <button type="button" wire:click="quickAdjust({{ $product->id }}, 'up')" class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 text-neutral-500 transition hover:bg-stone-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Increase stock') }}">
                                            <i class="fa-solid fa-plus text-xs"></i>
                                        </button>
                                        <button type="button" wire:click="startInlineEdit({{ $product->id }})" class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 text-neutral-500 transition hover:bg-stone-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Edit stock') }}">
                                            <i class="fa-solid fa-pencil text-xs"></i>
                                        </button>
                                        <button type="button" wire:click="toggleStatus({{ $product->id }})" class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 text-neutral-500 transition hover:bg-stone-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Toggle status') }}">
                                            <i class="fa-solid {{ $product->status === ProductStatus::Active ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                                        </button>
                                        <a href="{{ route('vendor.products.edit', $product) }}" wire:navigate class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-200 text-neutral-500 transition hover:bg-stone-100 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('Edit product') }}">
                                            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                                        </a>
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                            <flux:menu>
                                                <flux:menu.item wire:click="setStock({{ $product->id }}, 0)" wire:confirm="{{ __('Set this product stock to 0?') }}">
                                                    {{ __('Set to 0') }}
                                                </flux:menu.item>
                                                <flux:menu.item wire:click="setStock({{ $product->id }}, 100)">
                                                    {{ __('Set to 100') }}
                                                </flux:menu.item>
                                                <flux:menu.item wire:click="setStock({{ $product->id }}, 0)" wire:confirm="{{ __('Mark this product as out of stock?') }}">
                                                    {{ __('Mark as out of stock') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 px-4 pb-5">
                {{ $this->products->links() }}
            </div>
        @else
            @php($hasFilters = filled($search) || $statusFilter !== 'all' || filled($categoryFilter))
            <x-empty-state
                icon="fa-solid fa-boxes-stacked"
                :heading="__('No products found')"
                :body="$hasFilters ? __('Try adjusting your search or filters.') : __('You have not added any products yet.')"
                :action-label="__('Add your first product')"
                :action-route="route('vendor.products.create')"
                class="border-0"
            />
        @endif
    </section>

    <flux:modal wire:model="showBulkModal" class="max-w-sm">
        <div class="space-y-5 p-6">
            <flux:heading size="lg">
                @switch($bulkAction)
                    @case('add') {{ __('Add stock to :n products', ['n' => count($selectedIds)]) }} @break
                    @case('subtract') {{ __('Remove stock from :n products', ['n' => count($selectedIds)]) }} @break
                    @case('set') {{ __('Set stock for :n products', ['n' => count($selectedIds)]) }} @break
                    @case('activate') {{ __('Activate :n products', ['n' => count($selectedIds)]) }} @break
                    @case('deactivate') {{ __('Deactivate :n products', ['n' => count($selectedIds)]) }} @break
                @endswitch
            </flux:heading>

            @if (in_array($bulkAction, ['add', 'subtract', 'set'], true))
                <flux:field>
                    <flux:label>
                        @if ($bulkAction === 'add')
                            {{ __('Units to add') }}
                        @elseif ($bulkAction === 'subtract')
                            {{ __('Units to remove') }}
                        @else
                            {{ __('Set all to') }}
                        @endif
                    </flux:label>
                    <flux:input type="number" wire:model="bulkQuantity" min="0" max="999999" autofocus />
                    <flux:error name="bulkQuantity" />
                    <flux:description>
                        {{ __('This will be applied to all :n selected products.', ['n' => count($selectedIds)]) }}
                        @if ($bulkAction === 'subtract')
                            {{ __('Stock will not go below 0.') }}
                        @endif
                    </flux:description>
                </flux:field>
            @else
                <flux:text>
                    @if ($bulkAction === 'activate')
                        {{ __('All :n selected products will become visible to shoppers on the storefront.', ['n' => count($selectedIds)]) }}
                    @else
                        {{ __('All :n selected products will be hidden from the storefront.', ['n' => count($selectedIds)]) }}
                    @endif
                </flux:text>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showBulkModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="executeBulkAction" wire:loading.attr="disabled" wire:target="executeBulkAction">
                    <span wire:loading.remove wire:target="executeBulkAction">{{ __('Apply') }}</span>
                    <span wire:loading wire:target="executeBulkAction">{{ __('Applying…') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<?php

use App\Concerns\HasVendorGuard;
use App\Concerns\VendorProductValidationRules;
use App\Enums\AuditEvent;
use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create product')] class extends Component {
    use HasVendorGuard;
    use VendorProductValidationRules;
    use WithFileUploads;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $stock_quantity = '0';

    public string $categoryId = '';

    public string $status = 'inactive';

    public string $unit = '';

    public string $base_unit = '';

    public string $base_unit_quantity = '';

    public bool $showUnitConversion = false;

    public $productImageUpload = null;

    public function mount(): void
    {
        $this->status = ProductStatus::Inactive->value;
        $this->unit = ProductUnit::Piece->value;
    }

    public function save(): void
    {
        if (! $this->showUnitConversion) {
            $this->clearUnitConversion();
        }

        $validated = $this->validate($this->vendorProductRules(), $this->vendorProductValidationMessages());

        $imagePath = $this->productImageUpload->store('product-images', 'public');

        $product = Product::query()->create([
            'vendor_id' => $this->approvedVendorProfile()->getKey(),
            'category_id' => (int) $validated['categoryId'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock_quantity' => (int) $validated['stock_quantity'],
            'unit' => $validated['unit'],
            'base_unit' => blank($validated['base_unit'] ?? null) ? null : $validated['base_unit'],
            'base_unit_quantity' => blank($validated['base_unit_quantity'] ?? null) ? null : (float) $validated['base_unit_quantity'],
            'image' => $imagePath,
            'status' => $validated['status'],
        ]);

        AuditLogger::log(AuditEvent::ProductCreated, "Vendor created product '{$product->name}' (ID:{$product->id}).", $product);

        Flux::toast(variant: 'success', text: __('Product saved.'));

        $this->redirectRoute('vendor.products', navigate: true);
    }

    public function updatedCategoryId(): void
    {
        unset($this->selectedCategory, $this->selectedCategoryBreadcrumb, $this->suggestedUnitOptions, $this->otherUnitOptions);

        $suggestions = $this->suggestedUnitOptions;
        $this->unit = $suggestions !== [] ? $suggestions[0]->value : ProductUnit::Piece->value;

        $this->clearUnitConversion();
    }

    public function updatedUnit(): void
    {
        unset($this->selectedUnit, $this->suggestedBaseUnits, $this->pricePreview, $this->unitFormulaPreview);

        $this->clearUnitConversion();
    }

    public function updatedShowUnitConversion(bool $showUnitConversion): void
    {
        if (! $showUnitConversion) {
            $this->clearUnitConversion();

            return;
        }

        if (blank($this->base_unit) && $this->suggestedBaseUnits !== []) {
            $this->base_unit = $this->suggestedBaseUnits[0]['value'];
        }
    }

    #[Computed]
    public function categoryGroups(): Collection
    {
        return Category::query()
            ->leaves()
            ->with('parent:id,name')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Category $category) => $category->parent?->name ?? __('Other'));
    }

    #[Computed]
    public function selectedCategory(): ?Category
    {
        if (blank($this->categoryId)) {
            return null;
        }

        return Category::query()
            ->with('parent:id,name')
            ->find((int) $this->categoryId);
    }

    #[Computed]
    public function selectedCategoryBreadcrumb(): ?string
    {
        if ($this->selectedCategory === null) {
            return null;
        }

        return $this->selectedCategory->parent
            ? $this->selectedCategory->parent->name.' / '.$this->selectedCategory->name
            : $this->selectedCategory->name;
    }

    /**
     * @return list<ProductUnit>
     */
    #[Computed]
    public function suggestedUnitOptions(): array
    {
        return ProductUnit::suggestionsForCategory($this->selectedCategory?->slug ?? '');
    }

    /**
     * @return list<ProductUnit>
     */
    #[Computed]
    public function otherUnitOptions(): array
    {
        return array_values(array_filter(
            ProductUnit::cases(),
            fn (ProductUnit $unit): bool => ! in_array($unit, $this->suggestedUnitOptions, true),
        ));
    }

    #[Computed]
    public function selectedUnit(): ?ProductUnit
    {
        return ProductUnit::tryFrom($this->unit);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function suggestedBaseUnits(): array
    {
        return $this->selectedUnit?->suggestedBaseUnits() ?? [];
    }

    #[Computed]
    public function pricePreview(): ?string
    {
        if ($this->selectedUnit === null || ! is_numeric($this->price)) {
            return null;
        }

        return $this->selectedUnit->priceLabel((float) $this->price);
    }

    #[Computed]
    public function unitFormulaPreview(): ?string
    {
        if ($this->selectedUnit === null) {
            return null;
        }

        $price = is_numeric($this->price)
            ? "\u{20B1}".number_format((float) $this->price, 2)
            : "\u{20B1}0.00";

        return __('1 :label = 1 :abbr - :price per :abbr2', [
            'label' => strtolower($this->selectedUnit->label()),
            'abbr' => $this->selectedUnit->abbreviation(),
            'price' => $price,
            'abbr2' => $this->selectedUnit->abbreviation(),
        ]);
    }

    #[Computed]
    public function unitConversionPreview(): ?string
    {
        if (! $this->showUnitConversion || blank($this->base_unit) || blank($this->base_unit_quantity)) {
            return null;
        }

        if ($this->unitConversionPreviewTooLarge) {
            return __('Value is too large - please enter a realistic quantity.');
        }

        return $this->selectedUnit?->conversionLabel((float) $this->base_unit_quantity, $this->base_unit);
    }

    #[Computed]
    public function unitConversionPreviewTooLarge(): bool
    {
        if (! is_numeric($this->base_unit_quantity)) {
            return false;
        }

        $quantityValue = (float) $this->base_unit_quantity;

        if (! is_finite($quantityValue)) {
            return true;
        }

        $quantity = rtrim(rtrim(number_format($quantityValue, 4, '.', ''), '0'), '.');

        return mb_strlen(str_replace('.', '', $quantity)) > 20 || $quantityValue > 99999;
    }

    #[Computed]
    public function pricePerBaseUnit(): ?string
    {
        if (
            ! $this->showUnitConversion
            || blank($this->base_unit)
            || blank($this->base_unit_quantity)
            || blank($this->price)
            || ! is_numeric($this->price)
            || (float) $this->base_unit_quantity <= 0
        ) {
            return null;
        }

        return "\u{20B1}".number_format((float) $this->price / (float) $this->base_unit_quantity, 2).' per '.$this->base_unit;
    }

    #[Computed]
    public function customerConversionSummary(): ?string
    {
        if ($this->selectedUnit === null || blank($this->base_unit) || blank($this->base_unit_quantity) || ! is_numeric($this->price)) {
            return null;
        }

        $quantity = rtrim(rtrim(number_format((float) $this->base_unit_quantity, 4, '.', ''), '0'), '.');

        return __('1 :unit (:quantity :base) - :price', [
            'unit' => strtolower($this->selectedUnit->label()),
            'quantity' => $quantity,
            'base' => $this->base_unit,
            'price' => "\u{20B1}".number_format((float) $this->price, 2),
        ]);
    }

    private function clearUnitConversion(): void
    {
        $this->showUnitConversion = false;
        $this->base_unit = '';
        $this->base_unit_quantity = '';

        unset($this->suggestedBaseUnits, $this->unitConversionPreview, $this->unitConversionPreviewTooLarge, $this->pricePerBaseUnit, $this->customerConversionSummary);
    }
}; ?>

@php($selectedUnit = $this->selectedUnit)

<div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
    <form
        wire:submit="save"
        class="space-y-6"
        x-data="{
            dragOver: false,
            showConversion: @entangle('showUnitConversion').live,
            removePhoto() {
                this.$refs.productImageInput.value = null;
                $wire.$set('productImageUpload', null);
            },
            setDroppedFile(event) {
                const files = event.dataTransfer.files;

                if (! files.length) {
                    return;
                }

                this.$refs.productImageInput.files = files;
                this.dragOver = false;
                this.$refs.productImageInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }"
    >
        <header class="flex flex-col gap-4 border-b border-stone-200 pb-5 dark:border-white/10 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('vendor.products') }}" wire:navigate class="brand-hover-text inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 dark:text-zinc-400">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                    {{ __('Products') }}
                </a>
                <p class="brand-kicker !mb-2 mt-5">{{ __('Product builder') }}</p>
                <h1 class="brand-serif text-3xl font-bold text-neutral-950 dark:text-zinc-100 sm:text-4xl">
                    {{ __('New product') }}
                </h1>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <a href="{{ route('vendor.products') }}" wire:navigate class="brand-button-secondary active:scale-[0.96] justify-center">
                    {{ __('Cancel') }}
                </a>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save,productImageUpload" class="justify-center">
                    <span wire:loading.remove wire:target="save">{{ __('Save product') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving product...') }}</span>
                </flux:button>
            </div>
        </header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
            <main class="space-y-6">
                <section class="brand-panel overflow-hidden p-0">
                    <div class="grid lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                        <div
                            class="relative min-h-[24rem] bg-stone-100 dark:bg-zinc-900"
                            x-bind:class="dragOver ? 'ring-2 ring-inset ring-[var(--brand-500)]' : ''"
                            x-on:dragover.prevent="dragOver = true"
                            x-on:dragleave.prevent="dragOver = false"
                            x-on:drop.prevent="setDroppedFile($event)"
                        >
                            <input x-ref="productImageInput" id="product-image-upload" type="file" wire:model="productImageUpload" accept="image/jpeg,image/png,image/webp" class="sr-only">

                            @if ($productImageUpload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                <img src="{{ $productImageUpload->temporaryUrl() }}" alt="{{ __('Product preview') }}" class="h-full min-h-[24rem] w-full object-cover" loading="lazy">

                                <div class="absolute inset-x-4 bottom-4 flex flex-wrap items-center justify-between gap-3">
                                    <label for="product-image-upload" class="cursor-pointer rounded-lg bg-neutral-950/75 px-3.5 py-2 text-sm font-semibold text-white shadow-sm backdrop-blur transition hover:bg-neutral-950/90">
                                        {{ __('Change photo') }}
                                    </label>

                                    <button type="button" x-on:click="removePhoto()" class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/90 text-rose-600 shadow-sm backdrop-blur transition hover:bg-rose-50 dark:bg-zinc-950/85 dark:text-rose-300" aria-label="{{ __('Remove photo') }}">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                    </button>
                                </div>
                            @else
                                <label for="product-image-upload" class="flex h-full min-h-[24rem] w-full cursor-pointer flex-col items-center justify-center gap-4 border-2 border-dashed border-stone-300 p-8 text-center transition hover:border-[var(--brand-400)] hover:bg-[var(--brand-50)] dark:border-zinc-700 dark:hover:bg-[var(--brand-500)]/10">
                                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-[var(--brand-600)] text-white shadow-sm">
                                        <i class="fa-solid fa-camera text-xl"></i>
                                    </span>
                                    <span>
                                        <span class="block text-lg font-bold text-neutral-950 dark:text-zinc-100">{{ __('Add a product photo') }}</span>
                                        <span class="mt-2 block max-w-sm text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                                            {{ __('Square, well-lit photos look best in the storefront.') }}
                                        </span>
                                    </span>
                                    <span class="flex flex-wrap justify-center gap-2">
                                        <flux:badge size="sm">{{ __('JPG') }}</flux:badge>
                                        <flux:badge size="sm">{{ __('PNG') }}</flux:badge>
                                        <flux:badge size="sm">{{ __('WEBP') }}</flux:badge>
                                        <flux:badge size="sm">{{ __('max 3 MB') }}</flux:badge>
                                    </span>
                                </label>
                            @endif

                            <div wire:loading.flex wire:target="productImageUpload" class="absolute inset-0 hidden flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur dark:bg-zinc-950/75">
                                <span class="h-11 w-11 animate-spin rounded-full border-4 border-[var(--brand-200)] border-t-[var(--brand-600)]"></span>
                                <span class="text-sm font-semibold text-neutral-700 dark:text-zinc-200">{{ __('Uploading...') }}</span>
                            </div>
                        </div>

                        <div class="space-y-5 p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <span class="brand-soft-surface flex h-10 w-10 items-center justify-center rounded-xl">
                                    <i class="fa-solid fa-tag text-sm"></i>
                                </span>
                                <div>
                                    <p class="brand-kicker !mb-1">{{ __('Listing basics') }}</p>
                                    <h2 class="text-xl font-bold text-neutral-950 dark:text-zinc-100">{{ __('Name and story') }}</h2>
                                </div>
                            </div>

                            <div class="grid gap-4">
                                <flux:input wire:model.live.debounce.250ms="name" :label="__('Product name')" :placeholder="__('e.g. Sariwang Bangus - Davao Gulf')" maxlength="255" required class:input="h-11" />
                                <div class="-mt-2 text-right text-xs text-neutral-400 dark:text-zinc-500">{{ mb_strlen($name) }}/255</div>
                                <flux:error name="name" />

                                <flux:textarea wire:model.live.debounce.250ms="description" :label="__('Description')" :placeholder="__('Describe freshness, sourcing, how it is prepared, and anything local buyers should know before ordering.')" rows="7" maxlength="1000" required />
                                <div class="-mt-2 text-right text-xs text-neutral-400 dark:text-zinc-500">{{ mb_strlen($description) }}/1000</div>
                                <flux:error name="description" />
                            </div>

                            @error('productImageUpload')
                                <p class="text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="brand-panel p-5 sm:p-6">
                    <div class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                        <div class="space-y-5">
                            <div class="flex items-center gap-3">
                                <span class="brand-soft-surface flex h-10 w-10 items-center justify-center rounded-xl">
                                    <i class="fa-solid fa-layer-group text-sm"></i>
                                </span>
                                <div>
                                    <p class="brand-kicker !mb-1">{{ __('Category') }}</p>
                                    <h2 class="text-xl font-bold text-neutral-950 dark:text-zinc-100">{{ __('Where it belongs') }}</h2>
                                </div>
                            </div>

                            <flux:select wire:model.live="categoryId" :label="__('Category')" placeholder="{{ __('Choose a category') }}">
                                @foreach ($this->categoryGroups as $parentName => $categories)
                                    <optgroup label="{{ $parentName }}" wire:key="category-group-{{ str($parentName)->slug() }}">
                                        @foreach ($categories as $category)
                                            <flux:select.option :value="$category->id" :label="$category->name" wire:key="category-option-{{ $category->id }}" />
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </flux:select>
                            <flux:error name="categoryId" />

                            @if ($this->selectedCategoryBreadcrumb)
                                <span class="inline-flex w-fit rounded-lg border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-semibold text-neutral-600 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300">
                                    {{ $this->selectedCategoryBreadcrumb }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-5">
                            <div class="flex items-center gap-3">
                                <span class="brand-soft-surface flex h-10 w-10 items-center justify-center rounded-xl">
                                    <i class="fa-solid fa-peso-sign text-sm"></i>
                                </span>
                                <div>
                                    <p class="brand-kicker !mb-1">{{ __('Pricing & stock') }}</p>
                                    <h2 class="text-xl font-bold text-neutral-950 dark:text-zinc-100">{{ __('What shoppers can buy') }}</h2>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('Price') }}</flux:label>
                                    <flux:input.group>
                                        <flux:input.group.prefix>&#8369;</flux:input.group.prefix>
                                        <flux:input wire:model.live.debounce.250ms="price" type="number" inputmode="decimal" step="0.01" min="0.01" class:input="h-11" required />
                                    </flux:input.group>
                                    <flux:error name="price" />
                                    @if ($this->pricePreview)
                                        <p class="text-sm font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]">{{ $this->pricePreview }}</p>
                                    @endif
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Stock quantity') }}</flux:label>
                                    <flux:input.group>
                                        <flux:input wire:model.live.debounce.250ms="stock_quantity" type="number" min="0" step="1" class:input="h-11" required />
                                        @if ($selectedUnit)
                                            <flux:input.group.suffix>{{ $selectedUnit->abbreviation() }}</flux:input.group.suffix>
                                        @endif
                                    </flux:input.group>
                                    <flux:error name="stock_quantity" />
                                </flux:field>
                            </div>

                            <flux:callout icon="information-circle" variant="secondary">
                                <flux:callout.text>{{ __('Price is per selected selling unit. Stock is the number of those units available.') }}</flux:callout.text>
                            </flux:callout>
                        </div>
                    </div>
                </section>

                <section class="brand-panel p-5 sm:p-6" wire:key="unit-selector-{{ $categoryId }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="brand-soft-surface flex h-10 w-10 items-center justify-center rounded-xl">
                                <i class="fa-solid fa-ruler text-sm"></i>
                            </span>
                            <div>
                                <p class="brand-kicker !mb-1">{{ __('Selling unit') }}</p>
                                <h2 class="text-xl font-bold text-neutral-950 dark:text-zinc-100">{{ __('How this item is sold') }}</h2>
                            </div>
                        </div>

                        @if ($this->unitFormulaPreview)
                            <p class="rounded-lg border border-[var(--brand-200)] bg-[var(--brand-50)] px-4 py-3 text-sm font-semibold text-[var(--brand-800)] dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-200)]">
                                {{ $this->unitFormulaPreview }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->suggestedUnitOptions as $unitOption)
                            <button
                                type="button"
                                wire:key="suggested-unit-{{ $unitOption->value }}"
                                wire:click="$set('unit', '{{ $unitOption->value }}')"
                                aria-pressed="{{ $unit === $unitOption->value ? 'true' : 'false' }}"
                                @class([
                                    'relative min-h-16 rounded-lg border px-4 py-3 text-left transition active:scale-[0.98]',
                                    'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white shadow-sm' => $unit === $unitOption->value,
                                    'border-stone-200 bg-stone-50 text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $unit !== $unitOption->value,
                                ])
                            >
                                <span class="block text-sm font-bold">{{ __($unitOption->label()) }}</span>
                                <span class="mt-1 block text-xs opacity-75">{{ $unitOption->abbreviation() }}</span>
                                @if ($unit === $unitOption->value)
                                    <i class="fa-solid fa-check absolute right-3 top-3 text-xs"></i>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <details class="mt-4 rounded-lg border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-900">
                        <summary class="cursor-pointer text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                            {{ __('Show all units') }}
                        </summary>
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                            @foreach ($this->otherUnitOptions as $unitOption)
                                <button
                                    type="button"
                                    wire:key="other-unit-{{ $unitOption->value }}"
                                    wire:click="$set('unit', '{{ $unitOption->value }}')"
                                    @class([
                                        'relative rounded-lg border px-3 py-2 text-left transition active:scale-[0.98]',
                                        'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white' => $unit === $unitOption->value,
                                        'border-stone-200 bg-white text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-950 dark:text-zinc-300' => $unit !== $unitOption->value,
                                    ])
                                >
                                    <span class="block text-sm font-semibold">{{ __($unitOption->label()) }}</span>
                                    <span class="text-xs opacity-70">{{ $unitOption->abbreviation() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </details>

                    @error('unit')
                        <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror

                    <div class="mt-5 border-t border-stone-200 pt-5 dark:border-white/10">
                        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-dashed border-stone-300 bg-stone-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                            <span class="flex min-w-0 items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-[var(--brand-600)] shadow-sm dark:bg-zinc-950">
                                    <i class="fa-solid fa-arrows-left-right"></i>
                                </span>
                                <span>
                                    <span class="block text-sm font-bold text-neutral-900 dark:text-zinc-100">{{ __('Unit conversion') }}</span>
                                    <span class="mt-1 block text-xs leading-5 text-neutral-500 dark:text-zinc-400">{{ __('Optional - declare the base quantity per unit') }}</span>
                                </span>
                            </span>
                            <flux:checkbox wire:model.live="showUnitConversion" />
                        </label>

                        <div
                            x-cloak
                            x-show="showConversion"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 -translate-y-3 scale-98"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 -translate-y-3 scale-98"
                            class="mt-5 space-y-4"
                        >
                            <flux:callout icon="information-circle" variant="secondary">
                                <flux:callout.text>
                                    {{ __('For bulk items, show the real base quantity shoppers get per selling unit. Checkout pricing still works exactly the same.') }}
                                </flux:callout.text>
                            </flux:callout>

                            @if ($this->suggestedBaseUnits === [])
                                <flux:callout icon="information-circle" variant="secondary">
                                    <flux:callout.text>{{ __("Conversion is not needed - you're already selling by a base unit.") }}</flux:callout.text>
                                </flux:callout>
                            @else
                                <div class="grid gap-5 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                                    <div class="space-y-4">
                                        <flux:field>
                                            <flux:label>{{ __('Base unit') }}</flux:label>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($this->suggestedBaseUnits as $baseUnitOption)
                                                    <button
                                                        type="button"
                                                        wire:key="base-unit-{{ $baseUnitOption['value'] }}"
                                                        wire:click="$set('base_unit', '{{ $baseUnitOption['value'] }}')"
                                                        @class([
                                                            'rounded-lg border px-4 py-2 text-sm font-bold transition active:scale-[0.98]',
                                                            'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white' => $base_unit === $baseUnitOption['value'],
                                                            'border-stone-200 bg-white text-neutral-600 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $base_unit !== $baseUnitOption['value'],
                                                        ])
                                                    >
                                                        {{ $baseUnitOption['value'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                            <flux:error name="base_unit" />
                                        </flux:field>

                                        <flux:field>
                                            @php($usesCountBaseUnit = in_array($base_unit, ['piece', 'dozen', 'each', 'pair'], true))
                                            <flux:label>
                                                @if ($usesCountBaseUnit)
                                                    {{ __('How many :base per 1 :unit?', [
                                                        'base' => $base_unit === 'pair' ? __('pairs') : __('pieces'),
                                                        'unit' => $selectedUnit ? strtolower($selectedUnit->label()) : __('unit'),
                                                    ]) }}
                                                @else
                                                    {{ __('How many :base per 1 :unit?', [
                                                        'base' => $base_unit ?: __('base units'),
                                                        'unit' => $selectedUnit ? strtolower($selectedUnit->label()) : __('unit'),
                                                    ]) }}
                                                @endif
                                            </flux:label>
                                            <div class="relative">
                                                <flux:input type="number" wire:model.live.debounce.250ms="base_unit_quantity" step="0.001" min="0.001" max="99999" x-on:input="if (parseFloat($el.value) > 99999) { $el.value = 99999; $wire.$set('base_unit_quantity', '99999'); }" :placeholder="__('e.g. 25')" class="pr-16" />
                                                @if (filled($base_unit))
                                                    <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-stone-100 px-2.5 py-1 text-xs font-bold text-neutral-500 dark:bg-zinc-800 dark:text-zinc-300">{{ $base_unit }}</span>
                                                @endif
                                            </div>
                                            <flux:error name="base_unit_quantity" />
                                        </flux:field>
                                    </div>

                                    <div class="rounded-lg border border-[var(--brand-200)] bg-[var(--brand-50)] p-5 dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10">
                                        <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">
                                            <i class="fa-solid fa-box mr-2 text-[var(--brand-600)]"></i>
                                            {{ __('Conversion summary') }}
                                        </p>
                                        <div class="mt-4 space-y-2">
                                            @if ($this->unitConversionPreview)
                                                @if ($this->unitConversionPreviewTooLarge)
                                                    <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                                        {{ __('Value is too large - please enter a realistic quantity.') }}
                                                    </p>
                                                @else
                                                    <p class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $this->unitConversionPreview }}</p>
                                                    @if ($this->pricePerBaseUnit)
                                                        <p class="text-sm font-semibold text-neutral-600 dark:text-zinc-300">
                                                            {{ $this->pricePreview }} <span aria-hidden="true">&rarr;</span> {{ $this->pricePerBaseUnit }}
                                                        </p>
                                                    @endif
                                                    @if ($this->customerConversionSummary)
                                                        <p class="text-sm text-neutral-600 dark:text-zinc-300">
                                                            {{ __('Customers will see: ":summary"', ['summary' => $this->customerConversionSummary]) }}
                                                        </p>
                                                    @endif
                                                @endif
                                            @else
                                                <p class="text-sm leading-6 text-neutral-600 dark:text-zinc-300">
                                                    {{ __('Choose a base unit and quantity to preview the shopper-facing conversion.') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                <div class="xl:hidden">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save,productImageUpload" class="w-full justify-center py-4 text-base">
                        <span wire:loading.remove wire:target="save">{{ __('Save product') }}</span>
                        <span wire:loading wire:target="save">{{ __('Saving product...') }}</span>
                    </flux:button>
                </div>
            </main>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="brand-panel p-5">
                    <div class="flex items-center gap-3">
                        <span class="brand-soft-surface flex h-10 w-10 items-center justify-center rounded-xl">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </span>
                        <div>
                            <p class="brand-kicker !mb-1">{{ __('Publishing') }}</p>
                            <h2 class="text-xl font-bold text-neutral-950 dark:text-zinc-100">{{ __('Visibility') }}</h2>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3">
                        <button
                            type="button"
                            wire:click="$set('status', '{{ ProductStatus::Inactive->value }}')"
                            aria-pressed="{{ $status === ProductStatus::Inactive->value ? 'true' : 'false' }}"
                            @class([
                                'relative rounded-lg p-4 text-left transition active:scale-[0.98]',
                                'border-2 border-[var(--brand-500)] bg-[var(--brand-50)] text-[var(--brand-900)] dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-100)]' => $status === ProductStatus::Inactive->value,
                                'border border-stone-200 bg-stone-50 text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $status !== ProductStatus::Inactive->value,
                            ])
                        >
                            @if ($status === ProductStatus::Inactive->value)
                                <span class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-[var(--brand-600)] text-white">
                                    <i class="fa-solid fa-check text-[9px]"></i>
                                </span>
                            @endif
                            <i class="fa-solid fa-lock text-sm opacity-70"></i>
                            <p class="mt-2 text-sm font-bold">{{ __('Draft') }}</p>
                            <p class="mt-1 text-xs leading-5 opacity-75">{{ __('Hidden from storefront. Only you can see it.') }}</p>
                        </button>

                        <button
                            type="button"
                            wire:click="$set('status', '{{ ProductStatus::Active->value }}')"
                            aria-pressed="{{ $status === ProductStatus::Active->value ? 'true' : 'false' }}"
                            @class([
                                'relative rounded-lg p-4 text-left transition active:scale-[0.98]',
                                'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white' => $status === ProductStatus::Active->value,
                                'border border-stone-200 bg-[var(--brand-50)] text-[var(--brand-800)] hover:border-[var(--brand-300)] dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-200)]' => $status !== ProductStatus::Active->value,
                            ])
                        >
                            @if ($status === ProductStatus::Active->value)
                                <span class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-white text-[var(--brand-600)]">
                                    <i class="fa-solid fa-check text-[9px]"></i>
                                </span>
                            @endif
                            <i class="fa-solid fa-globe text-sm opacity-70"></i>
                            <p class="mt-2 text-sm font-bold">{{ __('Active') }}</p>
                            <p class="mt-1 text-xs leading-5 opacity-75">{{ __('Visible to shoppers on the storefront.') }}</p>
                        </button>
                    </div>

                    <flux:error name="status" />
                </section>

                <section class="brand-panel p-5">
                    <p class="brand-kicker !mb-3">{{ __('Listing snapshot') }}</p>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-500 dark:text-zinc-400">{{ __('Category') }}</span>
                            <span class="truncate font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->selectedCategoryBreadcrumb ?? __('Not selected') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-500 dark:text-zinc-400">{{ __('Unit') }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $selectedUnit?->label() ?? __('Not selected') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-500 dark:text-zinc-400">{{ __('Price') }}</span>
                            <span class="font-semibold text-neutral-900 dark:text-zinc-100">{{ $this->pricePreview ?? __('Not priced') }}</span>
                        </div>
                    </div>
                </section>

                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save,productImageUpload" class="hidden w-full justify-center py-3 xl:flex">
                    <span wire:loading.remove wire:target="save">{{ __('Save product') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving product...') }}</span>
                </flux:button>
            </aside>
        </div>
    </form>
</div>

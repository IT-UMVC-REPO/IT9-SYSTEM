<?php

use App\Concerns\HasVendorGuard;
use App\Concerns\VendorProductValidationRules;
use App\Enums\AuditEvent;
use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\StockManager;
use App\Support\CategoryUnitSuggestion;
use App\Support\PublicDiskUrl;
use App\Support\UnitConversionResult;
use App\Support\UnitFormatter;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit product')] class extends Component {
    use HasVendorGuard;
    use VendorProductValidationRules;
    use WithFileUploads;

    public int $productId;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $stock_quantity = '0';

    public string $categoryId = '';

    public string $status = 'inactive';

    public string $unit = '';

    public string $conversion_unit = '';

    public string $conversion_unit_quantity = '';

    public bool $showUnitConversion = false;

    public ?int $primaryVariantId = null;

    /**
     * @var array<int, array{id: int|null, unit: string, price: string, stock_quantity: string, conversion_unit: string, conversion_unit_quantity: string}>
     */
    public array $additionalVariants = [];

    public string $defaultVariantKey = 'primary';

    public $productImageUpload = null;

    public ?string $currentImage = null;

    public function mount(Product $product): void
    {
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);

            return;
        }

        abort_if($product->vendor_id !== $this->approvedVendorProfile()->getKey(), 403);

        $this->productId = $product->getKey();
        $this->name = $product->name;
        $this->description = $product->description;
        $this->price = (string) $product->price;
        $this->stock_quantity = (string) $product->stock_quantity;
        $this->categoryId = (string) $product->category_id;
        $this->status = $product->status->value;
        $this->unit = $product->unit->value;
        $this->conversion_unit = $product->conversion_unit?->value ?? '';
        $this->conversion_unit_quantity = $product->conversion_unit_quantity === null ? '' : (string) $product->conversion_unit_quantity;
        $this->showUnitConversion = filled($product->conversion_unit) && $product->conversion_unit_quantity !== null;
        $this->currentImage = $product->getRawOriginal('image');

        $this->mountVariantRows($product);
    }

    public function update(): void
    {
        if (! $this->showUnitConversion) {
            $this->clearUnitConversion();
        }

        $product = Product::query()
            ->forVendor($this->approvedVendorProfile()->getKey())
            ->findOrFail($this->productId);

        $validated = $this->validate($this->vendorProductRules(requireImage: false), $this->vendorProductValidationMessages());
        $variantRows = $this->validatedVariantRows($validated);
        $defaultVariant = collect($variantRows)->firstWhere('is_default', true) ?? $variantRows[0];
        $canonicalStock = $this->canonicalStockAttributes($defaultVariant, $variantRows);

        $attributes = [
            'category_id' => (int) $validated['categoryId'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $defaultVariant['price'],
            'stock_quantity' => $defaultVariant['stock_quantity'],
            'canonical_stock_unit' => $canonicalStock['unit'],
            'canonical_stock_quantity' => $canonicalStock['quantity'],
            'unit' => $defaultVariant['unit'],
            'conversion_unit' => $defaultVariant['conversion_unit'],
            'conversion_unit_quantity' => $defaultVariant['conversion_unit_quantity'],
            'status' => $validated['status'],
        ];

        if ($this->productImageUpload !== null) {
            $oldImagePath = $product->getRawOriginal('image');

            if (
                filled($oldImagePath)
                && ! Str::startsWith($oldImagePath, ['http://', 'https://', '//'])
                && Storage::disk('public')->exists($oldImagePath)
            ) {
                Storage::disk('public')->delete($oldImagePath);
            }

            $attributes['image'] = $this->productImageUpload->store('product-images', 'public');
            $this->currentImage = $attributes['image'];
            $this->productImageUpload = null;
        }

        $product->update($attributes);
        $this->syncVariants($product, $variantRows);

        AuditLogger::log(AuditEvent::ProductUpdated, "Vendor updated product '{$product->name}' (ID:{$product->id}).", $product);

        Flux::toast(variant: 'success', text: __('Listing updated.'));

        $this->redirectRoute('vendor.products', navigate: true);
    }

    #[On('delete-product-listing-confirmed')]
    public function deleteListing(): void
    {
        $product = Product::query()
            ->forVendor($this->approvedVendorProfile()->getKey())
            ->findOrFail($this->productId);

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
        unset($this->selectedUnit, $this->conversionUnitOptions, $this->pricePreview, $this->unitFormulaPreview);

        $this->clearUnitConversion();
    }

    public function updatedShowUnitConversion(bool $showUnitConversion): void
    {
        if (! $showUnitConversion) {
            $this->clearUnitConversion();

            return;
        }

        if (blank($this->conversion_unit) && $this->conversionUnitOptions !== []) {
            $this->conversion_unit = $this->conversionUnitOptions[0]['value'];
        }
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'additionalVariants.')) {
            $this->resetValidation($property);
        }
    }

    public function addVariant(): void
    {
        $this->additionalVariants[] = $this->blankVariantRow();
    }

    public function removeVariant(int $index): void
    {
        if ($this->defaultVariantKey === 'variant-'.$index) {
            Flux::toast(variant: 'warning', text: __('Choose another default variant before deleting this one.'));

            return;
        }

        unset($this->additionalVariants[$index]);
    }

    public function setDefaultVariant(string $variantKey): void
    {
        if ($variantKey === 'primary' || str_starts_with($variantKey, 'variant-')) {
            $this->defaultVariantKey = $variantKey;
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
            ? $this->selectedCategory->parent->name.' › '.$this->selectedCategory->name
            : $this->selectedCategory->name;
    }

    #[Computed]
    public function currentImageUrl(): ?string
    {
        if (blank($this->currentImage)) {
            return null;
        }

        return PublicDiskUrl::nullable($this->currentImage);
    }

    /**
     * @return list<ProductUnit>
     */
    #[Computed]
    public function suggestedUnitOptions(): array
    {
        return CategoryUnitSuggestion::forCategory($this->selectedCategory?->slug ?? '');
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
    public function conversionUnitOptions(): array
    {
        if ($this->selectedUnit === null) {
            return [];
        }

        return array_map(
            fn (ProductUnit $unit): array => [
                'value' => $unit->value,
                'label' => $unit->label().' ('.$unit->symbol().')',
            ],
            array_values(array_filter(
                ProductUnit::cases(),
                fn (ProductUnit $unit): bool => $unit !== $this->selectedUnit
                    && (
                        $this->selectedUnit->isCountBased()
                            ? true
                            : $this->selectedUnit->type() === $unit->type()
                    ),
            )),
        );
    }

    #[Computed]
    public function pricePreview(): ?string
    {
        if ($this->selectedUnit === null || ! is_numeric($this->price)) {
            return null;
        }

        return UnitFormatter::pricePerUnit($this->selectedUnit, (float) $this->price);
    }

    #[Computed]
    public function unitFormulaPreview(): ?string
    {
        if ($this->selectedUnit === null) {
            return null;
        }

        $price = is_numeric($this->price)
            ? UnitFormatter::currency((float) $this->price)
            : UnitFormatter::currency(0);

        return __('1 :label = 1 :abbr - :price per :abbr2', [
            'label' => strtolower($this->selectedUnit->label()),
            'abbr' => $this->selectedUnit->abbreviation(),
            'price' => $price,
            'abbr2' => $this->selectedUnit->abbreviation(),
        ]);
    }

    #[Computed]
    public function unitConversionResult(): ?UnitConversionResult
    {
        $conversionUnit = ProductUnit::tryFrom($this->conversion_unit);

        if (
            ! $this->showUnitConversion
            || $this->selectedUnit === null
            || $conversionUnit === null
            || blank($this->conversion_unit_quantity)
            || ! is_numeric($this->conversion_unit_quantity)
        ) {
            return null;
        }

        if ((float) $this->conversion_unit_quantity <= 0 || (float) $this->conversion_unit_quantity > 99999) {
            return null;
        }

        return new UnitConversionResult(
            fromUnit: $this->selectedUnit,
            fromQuantity: 1,
            toUnit: $conversionUnit,
            toQuantity: (float) $this->conversion_unit_quantity,
            fromUnitPrice: is_numeric($this->price) ? (float) $this->price : null,
        );
    }

    private function mountVariantRows(Product $product): void
    {
        $product->loadMissing('unitVariants');
        $defaultVariant = $product->unitVariants->firstWhere('is_default', true);

        if ($defaultVariant !== null) {
            $this->primaryVariantId = $defaultVariant->getKey();
            $this->unit = $defaultVariant->unit->value;
            $this->price = (string) $defaultVariant->price;
            $this->stock_quantity = (string) $defaultVariant->stock_quantity;
            $this->conversion_unit = $defaultVariant->conversion_unit?->value ?? '';
            $this->conversion_unit_quantity = $defaultVariant->conversion_unit_quantity === null ? '' : (string) $defaultVariant->conversion_unit_quantity;
            $this->showUnitConversion = filled($defaultVariant->conversion_unit) && $defaultVariant->conversion_unit_quantity !== null;
        }

        $this->additionalVariants = $product->unitVariants
            ->reject(fn ($variant): bool => $defaultVariant !== null && $variant->is($defaultVariant))
            ->values()
            ->map(fn ($variant): array => [
                'id' => $variant->getKey(),
                'unit' => $variant->unit->value,
                'price' => (string) $variant->price,
                'stock_quantity' => (string) $variant->stock_quantity,
                'conversion_unit' => $variant->conversion_unit?->value ?? '',
                'conversion_unit_quantity' => $variant->conversion_unit_quantity === null ? '' : (string) $variant->conversion_unit_quantity,
            ])
            ->all();
    }

    /**
     * @return array{id: int|null, unit: string, price: string, stock_quantity: string, conversion_unit: string, conversion_unit_quantity: string}
     */
    private function blankVariantRow(): array
    {
        return [
            'id' => null,
            'unit' => ProductUnit::Piece->value,
            'price' => '',
            'stock_quantity' => '0',
            'conversion_unit' => '',
            'conversion_unit_quantity' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{id: int|null, key: string, unit: string, price: float, stock_quantity: int, conversion_unit: string|null, conversion_unit_quantity: float|null, is_default: bool}>
     */
    private function validatedVariantRows(array $validated): array
    {
        $validator = Validator::make([
            'additionalVariants' => $this->additionalVariants,
            'defaultVariantKey' => $this->defaultVariantKey,
        ], [
            'additionalVariants' => ['array'],
            'additionalVariants.*.id' => ['nullable', 'integer'],
            'additionalVariants.*.unit' => ['required', Rule::enum(ProductUnit::class)],
            'additionalVariants.*.price' => ['required', 'numeric', 'min:0.01'],
            'additionalVariants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'additionalVariants.*.conversion_unit' => ['nullable', Rule::enum(ProductUnit::class)],
            'additionalVariants.*.conversion_unit_quantity' => ['nullable', 'numeric', 'min:0.001', 'max:99999', 'required_with:additionalVariants.*.conversion_unit'],
            'defaultVariantKey' => ['required', 'string'],
        ], [
            'additionalVariants.*.conversion_unit_quantity.max' => __('Conversion quantity cannot exceed 99,999.'),
        ], $this->variantValidationAttributes());

        $validator->after(function ($validator) use ($validated): void {
            $seenUnits = [$validated['unit'] => 'unit'];
            $defaultExists = $this->defaultVariantKey === 'primary';

            foreach ($this->additionalVariants as $index => $variant) {
                $unit = $variant['unit'] ?? null;

                if ($unit !== null && isset($seenUnits[$unit])) {
                    $validator->errors()->add("additionalVariants.{$index}.unit", __('Each variant must use a different unit.'));
                }

                if ($unit !== null) {
                    $seenUnits[$unit] = "additionalVariants.{$index}.unit";
                }

                if ($this->defaultVariantKey === 'variant-'.$index) {
                    $defaultExists = true;
                }
            }

            if (! $defaultExists) {
                $validator->errors()->add('defaultVariantKey', __('Choose a default selling variant.'));
            }
        });

        $validatedVariants = $validator->validate();
        $rows = [
            [
                'id' => $this->primaryVariantId,
                'key' => 'primary',
                'unit' => $validated['unit'],
                'price' => (float) $validated['price'],
                'stock_quantity' => (int) $validated['stock_quantity'],
                'conversion_unit' => blank($validated['conversion_unit'] ?? null) ? null : $validated['conversion_unit'],
                'conversion_unit_quantity' => blank($validated['conversion_unit_quantity'] ?? null) ? null : (float) $validated['conversion_unit_quantity'],
                'is_default' => $this->defaultVariantKey === 'primary',
            ],
        ];

        foreach (($validatedVariants['additionalVariants'] ?? []) as $index => $variant) {
            $rows[] = [
                'id' => blank($variant['id'] ?? null) ? null : (int) $variant['id'],
                'key' => 'variant-'.$index,
                'unit' => $variant['unit'],
                'price' => (float) $variant['price'],
                'stock_quantity' => (int) $variant['stock_quantity'],
                'conversion_unit' => blank($variant['conversion_unit'] ?? null) ? null : $variant['conversion_unit'],
                'conversion_unit_quantity' => blank($variant['conversion_unit_quantity'] ?? null) ? null : (float) $variant['conversion_unit_quantity'],
                'is_default' => $this->defaultVariantKey === 'variant-'.$index,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function variantValidationAttributes(): array
    {
        return [
            'additionalVariants.*.unit' => __('variant unit'),
            'additionalVariants.*.price' => __('variant price'),
            'additionalVariants.*.stock_quantity' => __('variant stock'),
            'additionalVariants.*.conversion_unit' => __('variant conversion unit'),
            'additionalVariants.*.conversion_unit_quantity' => __('variant conversion quantity'),
            'defaultVariantKey' => __('default selling variant'),
        ];
    }

    /**
     * @param  array{unit: string, stock_quantity: int}  $defaultVariant
     * @param  list<array{unit: string}>  $variantRows
     * @return array{unit: string|null, quantity: float|null}
     */
    private function canonicalStockAttributes(array $defaultVariant, array $variantRows): array
    {
        $defaultUnit = ProductUnit::from($defaultVariant['unit']);
        $canUseCanonical = $defaultUnit->conversionFactor() !== null
            && collect($variantRows)->every(fn (array $row): bool => ProductUnit::from($row['unit'])->isConvertibleTo($defaultUnit));

        return [
            'unit' => $canUseCanonical ? $defaultUnit->baseUnit()?->value : null,
            'quantity' => $canUseCanonical ? $defaultVariant['stock_quantity'] * $defaultUnit->conversionFactor() : null,
        ];
    }

    /**
     * @param  list<array{id: int|null, unit: string, price: float, stock_quantity: int, conversion_unit: string|null, conversion_unit_quantity: float|null, is_default: bool}>  $variantRows
     */
    private function syncVariants(Product $product, array $variantRows): void
    {
        $submittedIds = [];
        $defaultVariant = null;

        foreach ($variantRows as $sortOrder => $variant) {
            $unitVariant = $variant['id'] !== null
                ? $product->unitVariants()->whereKey($variant['id'])->first()
                : $product->unitVariants()->where('unit', $variant['unit'])->first();

            $unitVariant ??= $product->unitVariants()->make();
            $unitVariant->fill([
                'unit' => $variant['unit'],
                'price' => $variant['price'],
                'stock_quantity' => $variant['stock_quantity'],
                'conversion_unit' => $variant['conversion_unit'],
                'conversion_unit_quantity' => $variant['conversion_unit_quantity'],
                'is_default' => $variant['is_default'],
                'sort_order' => $sortOrder,
            ]);
            $unitVariant->save();

            $submittedIds[] = $unitVariant->getKey();

            if ($variant['is_default']) {
                $defaultVariant = $unitVariant;
            }
        }

        $product->unitVariants()
            ->when($submittedIds !== [], fn ($query) => $query->whereNotIn('id', $submittedIds))
            ->delete();

        if ($defaultVariant !== null && $product->canonical_stock_unit !== null) {
            app(StockManager::class)->setStock($defaultVariant, (int) $defaultVariant->stock_quantity);
        }
    }

    private function clearUnitConversion(): void
    {
        $this->showUnitConversion = false;
        $this->conversion_unit = '';
        $this->conversion_unit_quantity = '';

        unset($this->conversionUnitOptions, $this->unitConversionResult);
    }

}; ?>

<div class="mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:px-8">
    <form
        wire:submit="update"
        class="brand-panel overflow-hidden p-0"
        style="border-top: 3px solid var(--brand-600);"
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
        <div
            x-data="{ scrolled: false }"
            x-on:scroll.window.passive="scrolled = window.scrollY > 16"
            x-bind:class="scrolled ? 'shadow-lg' : ''"
            class="sticky top-0 z-10 border-b border-stone-200 bg-white/85 px-5 py-4 backdrop-blur transition-shadow duration-200 dark:border-white/10 dark:bg-zinc-950/80 sm:px-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <a href="{{ route('vendor.products') }}" wire:navigate class="brand-hover-text inline-flex items-center gap-2 text-xs font-semibold text-neutral-500 dark:text-zinc-400">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i>
                        {{ __('Products') }}
                    </a>
                    <h1 class="brand-serif text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('Edit product') }}
                    </h1>
                </div>

                <flux:button
                    variant="primary"
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="update,productImageUpload"
                    class="w-full transition-all duration-150 active:scale-[0.97] sm:w-auto"
                >
                    <span wire:loading.remove wire:target="update">{{ __('Save product') }}</span>
                    <span wire:loading wire:target="update">{{ __('Saving product…') }}</span>
                </flux:button>
            </div>
        </div>

        <div class="grid gap-8 p-5 sm:p-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:p-8">
            <section class="space-y-4 self-start">
                <div
                    class="relative flex min-h-[320px] overflow-hidden rounded-[1.75rem] border-2 border-dashed border-stone-300 bg-stone-50 transition-all duration-250 hover:bg-stone-100 dark:border-zinc-600 dark:bg-zinc-900/70 dark:hover:bg-zinc-900 lg:min-h-[320px]"
                    x-bind:class="dragOver ? 'border-[var(--brand-400)] bg-[var(--brand-50)] dark:bg-[var(--brand-500)]/10' : ''"
                    x-on:dragover.prevent="dragOver = true"
                    x-on:dragleave.prevent="dragOver = false"
                    x-on:drop.prevent="setDroppedFile($event)"
                >
                    <input
                        x-ref="productImageInput"
                        id="product-image-upload"
                        type="file"
                        wire:model="productImageUpload"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                    >

                    @php($productImagePreviewUrl = $productImageUpload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile ? $productImageUpload->temporaryUrl() : $this->currentImageUrl)

                    @if ($productImagePreviewUrl)
                        <div class="absolute inset-0">
                            <img
                                src="{{ $productImagePreviewUrl }}"
                                alt="{{ __('Product preview') }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >

                            <label for="product-image-upload" class="absolute bottom-4 right-4 cursor-pointer rounded-full bg-neutral-950/70 px-4 py-2 text-sm font-semibold text-white shadow-sm backdrop-blur transition hover:bg-neutral-950/85">
                                {{ __('Change photo') }}
                            </label>

                            @if ($productImageUpload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                <button
                                    type="button"
                                    x-on:click="removePhoto()"
                                    class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-rose-600 shadow-sm backdrop-blur transition hover:bg-rose-50 dark:bg-zinc-950/85 dark:text-rose-300"
                                    aria-label="{{ __('Remove photo') }}"
                                >
                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                </button>
                            @endif
                        </div>
                    @else
                        <label for="product-image-upload" class="flex min-h-full w-full cursor-pointer flex-col items-center justify-center gap-4 p-8 text-center">
                            <span class="brand-soft-surface flex h-16 w-16 items-center justify-center rounded-2xl">
                                <i class="fa-solid fa-camera text-2xl"></i>
                            </span>
                            <span class="space-y-2">
                                <span class="block text-lg font-bold text-neutral-900 dark:text-zinc-100">{{ __('Add a product photo') }}</span>
                                <span class="block max-w-sm text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                    {{ __('Use a clean, well-lit photo of the actual item customers will receive. Square photos work best.') }}
                                </span>
                            </span>
                        </label>
                    @endif

                    <div
                        wire:loading.flex
                        wire:target="productImageUpload"
                        class="absolute inset-0 hidden flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur dark:bg-zinc-950/75"
                    >
                        <div class="absolute inset-6 animate-pulse rounded-[1.5rem] bg-stone-200/70 dark:bg-zinc-700/60"></div>
                        <span class="relative flex h-12 w-12 animate-spin rounded-full border-4 border-[var(--brand-200)] border-t-[var(--brand-600)]"></span>
                        <span class="relative text-sm font-semibold text-neutral-700 dark:text-zinc-200">{{ __('Uploading...') }}</span>
                    </div>
                </div>

                @error('productImageUpload')
                    <p class="text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap gap-2">
                    <flux:badge size="sm">{{ __('JPG') }}</flux:badge>
                    <flux:badge size="sm">{{ __('PNG') }}</flux:badge>
                    <flux:badge size="sm">{{ __('WEBP') }}</flux:badge>
                    <flux:badge size="sm">{{ __('max 3 MB') }}</flux:badge>
                </div>

                <details class="rounded-2xl border border-stone-200 bg-white/70 p-4 dark:border-white/10 dark:bg-zinc-900/60">
                    <summary class="cursor-pointer text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                        {{ __('Tips for a great photo') }}
                    </summary>
                    <ul class="mt-3 space-y-2 text-sm leading-6 text-neutral-500 dark:text-zinc-400">
                        <li>{{ __('Use a plain background.') }}</li>
                        <li>{{ __('Choose good lighting.') }}</li>
                        <li>{{ __('Keep the whole product visible.') }}</li>
                        <li>{{ __('Avoid watermarks.') }}</li>
                    </ul>
                </details>
            </section>

            <section class="space-y-8">
                @php($selectedUnit = $this->selectedUnit)

                <section class="space-y-5 border-b border-stone-200 pb-8 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-tag text-[var(--brand-600)]"></i>
                        <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-neutral-900 dark:text-zinc-100">{{ __('Listing basics') }}</h2>
                    </div>

                    <flux:field>
                        <div class="flex items-center justify-between gap-3">
                            <flux:label>{{ __('Product name') }}</flux:label>
                            <span class="text-xs font-medium text-neutral-400 dark:text-zinc-500">{{ mb_strlen($name) }}/255</span>
                        </div>
                        <flux:input wire:model.live.debounce.250ms="name" type="text" maxlength="255" :placeholder="__('e.g. Sariwang Bangus - Davao Gulf')" required />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <div class="flex items-center justify-between gap-3">
                            <flux:label>{{ __('Description') }}</flux:label>
                            <span class="text-xs font-medium text-neutral-400 dark:text-zinc-500">{{ mb_strlen($description) }}/1000</span>
                        </div>
                        <flux:textarea wire:model.live.debounce.250ms="description" rows="4" maxlength="1000" :placeholder="__('Describe freshness, sourcing, how it is prepared, and anything local buyers should know before ordering.')" required />
                        <flux:error name="description" />
                    </flux:field>
                </section>

                <section class="space-y-5 border-b border-stone-200 pb-8 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-peso-sign text-[var(--brand-600)]"></i>
                        <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-neutral-900 dark:text-zinc-100">{{ __('Pricing & stock') }}</h2>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(11rem,0.85fr)] lg:items-end">
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
                        <flux:callout.text>{{ __('Set the price per single unit. Customers choose how many units to add to cart.') }}</flux:callout.text>
                    </flux:callout>
                </section>

                <section class="space-y-5 border-b border-stone-200 pb-8 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-layer-group text-[var(--brand-600)]"></i>
                        <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-neutral-900 dark:text-zinc-100">{{ __('Category') }}</h2>
                    </div>

                    <flux:select wire:model.live="categoryId" :label="__('Category')" placeholder="{{ __('Choose a category') }}">
                        @foreach ($this->categoryGroups as $parentName => $categories)
                            <optgroup label="{{ $parentName }}">
                                @foreach ($categories as $category)
                                    <flux:select.option :value="$category->id" :label="$category->name" />
                                @endforeach
                            </optgroup>
                        @endforeach
                    </flux:select>

                    @if ($this->selectedCategoryBreadcrumb)
                        <span class="inline-flex w-fit rounded-full border border-stone-200 bg-stone-50 px-3 py-1 text-xs font-semibold text-neutral-500 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $this->selectedCategoryBreadcrumb }}
                        </span>
                    @endif
                </section>

                <section class="space-y-4 border-b border-stone-200 pb-8 dark:border-white/10" wire:key="unit-selector-{{ $categoryId }}">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-ruler text-[var(--brand-600)]"></i>
                        <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-neutral-900 dark:text-zinc-100">{{ __('Selling unit') }}</h2>
                    </div>

                    <div class="space-y-3">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Suggested units') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($this->suggestedUnitOptions as $unitOption)
                                <button
                                    type="button"
                                    wire:click="$set('unit', '{{ $unitOption->value }}')"
                                    @class([
                                        'relative rounded-2xl border px-4 py-3 text-left transition-all duration-150 active:scale-[0.97]',
                                        'border-[var(--brand-600)] bg-[var(--brand-600)] text-white shadow-sm' => $unit === $unitOption->value,
                                        'border-stone-200 bg-white text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $unit !== $unitOption->value,
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
                    </div>

                    <details class="mt-2 rounded-2xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-900">
                        <summary class="cursor-pointer text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Show all units') }}</summary>
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($this->otherUnitOptions as $unitOption)
                                <button
                                    type="button"
                                    wire:click="$set('unit', '{{ $unitOption->value }}')"
                                    @class([
                                        'relative rounded-xl border px-3 py-2 text-left transition-all duration-150 active:scale-[0.97]',
                                        'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' => $unit === $unitOption->value,
                                        'border-stone-200 bg-white text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-950 dark:text-zinc-300' => $unit !== $unitOption->value,
                                    ])
                                >
                                    <span class="block text-sm font-semibold">{{ __($unitOption->label()) }}</span>
                                    <span class="text-xs opacity-70">{{ $unitOption->abbreviation() }}</span>
                                    @if ($unit === $unitOption->value)
                                        <i class="fa-solid fa-check absolute right-2 top-2 text-[10px]"></i>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </details>

                    @error('unit')
                        <p class="text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror

                    @if ($this->unitFormulaPreview)
                        <p class="mt-3 rounded-2xl border border-[var(--brand-200)] bg-[var(--brand-50)] px-4 py-3 text-sm font-semibold text-[var(--brand-800)] dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-200)]">
                            {{ $this->unitFormulaPreview }}
                        </p>
                    @endif
                </section>

                <section class="space-y-4 border-b border-stone-200 pb-8 dark:border-white/10">
                    <label class="brand-soft-surface flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-dashed border-stone-300 p-4 dark:border-zinc-600">
                        <span class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[var(--brand-600)] shadow-sm dark:bg-zinc-900">
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
                        x-show="showConversion"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 -translate-y-3 scale-98"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-y-3 scale-98"
                        class="space-y-4"
                    >
                        <flux:callout icon="information-circle" variant="secondary">
                            <flux:callout.text>
                                {{ __('Help customers understand the actual quantity. For example, if you sell rice by the sack, you can state that 1 sack = 25 kg. This is shown on the product page alongside your price but does not affect checkout or pricing - it is purely informational.') }}
                            </flux:callout.text>
                        </flux:callout>

                        @if ($this->conversionUnitOptions === [])
                            <flux:callout icon="information-circle" variant="secondary">
                                <flux:callout.text>{{ __("Conversion is not needed - you're already selling by a conversion unit.") }}</flux:callout.text>
                            </flux:callout>
                        @else
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('Conversion unit') }}</flux:label>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($this->conversionUnitOptions as $baseUnitOption)
                                            <button
                                                type="button"
                                                wire:click="$set('conversion_unit', '{{ $baseUnitOption['value'] }}')"
                                                @class([
                                                    'rounded-xl border px-4 py-2 text-sm font-bold transition-all duration-150 active:scale-[0.97]',
                                                    'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' => $conversion_unit === $baseUnitOption['value'],
                                                    'border-stone-200 bg-white text-neutral-600 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' => $conversion_unit !== $baseUnitOption['value'],
                                                ])
                                            >
                                                {{ $baseUnitOption['value'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                    <flux:error name="conversion_unit" />
                                </flux:field>

                                <flux:field>
                                    @php($usesCountBaseUnit = in_array($conversion_unit, ['piece', 'dozen', 'each', 'pair'], true))
                                    <flux:label>
                                        @if ($usesCountBaseUnit)
                                            {{ __('How many :base per 1 :unit?', [
                                                'base' => $conversion_unit === 'pair' ? __('pairs') : __('pieces'),
                                                'unit' => $selectedUnit ? strtolower($selectedUnit->label()) : __('unit'),
                                            ]) }}
                                        @else
                                            {{ __('How many :base per 1 :unit?', [
                                                'base' => $conversion_unit ?: __('conversion units'),
                                                'unit' => $selectedUnit ? strtolower($selectedUnit->label()) : __('unit'),
                                            ]) }}
                                        @endif
                                    </flux:label>
                                    <div class="relative">
                                        <flux:input type="number" wire:model.live.debounce.250ms="conversion_unit_quantity" step="0.001" min="0.001" max="99999" x-on:input="if (parseFloat($el.value) > 99999) { $el.value = 99999; $wire.$set('conversion_unit_quantity', '99999'); }" :placeholder="__('e.g. 25')" class="pr-16" />
                                        @if (filled($conversion_unit))
                                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-bold text-neutral-500 dark:bg-zinc-800 dark:text-zinc-300">{{ $conversion_unit }}</span>
                                        @endif
                                    </div>
                                    <flux:error name="conversion_unit_quantity" />
                                </flux:field>
                            </div>

                            @if ($this->unitConversionResult)
                                <div class="rounded-2xl border-2 border-[var(--brand-500)] bg-[color:color-mix(in_oklab,var(--brand-50),white_20%)] p-5 transition-all duration-300 ease-out dark:bg-zinc-800/60">
                                    <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">
                                        <i class="fa-solid fa-box mr-2 text-[var(--brand-600)]"></i>
                                        {{ __('Conversion summary') }}
                                    </p>
                                    <div class="mt-4 space-y-2">
                                        <p class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ $this->unitConversionResult->displayString }}</p>
                                        @if ($this->unitConversionResult->pricePerConversionUnitLabel())
                                            <p class="text-sm font-semibold text-neutral-500 dark:text-zinc-400">
                                                {{ $this->pricePreview }} <span class="mx-2">→</span> {{ $this->unitConversionResult->pricePerConversionUnitLabel() }}
                                            </p>
                                        @endif
                                        <p class="text-sm text-neutral-500 dark:text-zinc-400">
                                            {{ __('Customers will see: ":summary"', ['summary' => $this->unitConversionResult->displayString]) }}
                                        </p>
                                    </div>
                                </div>
                            @endif                        @endif
                    </div>
                </section>

                <section class="space-y-5 border-b border-stone-200 pb-8 dark:border-white/10">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-boxes-stacked text-[var(--brand-600)]"></i>
                            <div>
                                <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-neutral-900 dark:text-zinc-100">{{ __('Selling variants') }}</h2>
                                <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">{{ __('Offer this listing in multiple unit configurations while keeping one catalog product.') }}</p>
                            </div>
                        </div>

                        <flux:button type="button" variant="filled" icon="plus" wire:click="addVariant">
                            {{ __('Add variant') }}
                        </flux:button>
                    </div>

                    <div class="grid gap-4">
                        <div @class([
                            'rounded-2xl border p-4',
                            'border-[var(--brand-500)] bg-[var(--brand-50)] dark:border-[var(--brand-500)]/40 dark:bg-[var(--brand-500)]/10' => $defaultVariantKey === 'primary',
                            'border-stone-200 bg-stone-50 dark:border-white/10 dark:bg-zinc-900' => $defaultVariantKey !== 'primary',
                        ])>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">{{ __('Primary variant') }}</p>
                                    <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                                        {{ $this->pricePreview ?? __('Set a price') }} <span aria-hidden="true">&middot;</span> {{ $selectedUnit ? __($selectedUnit->label()) : __('Selling unit') }} <span aria-hidden="true">&middot;</span> {{ __(':stock in stock', ['stock' => $stock_quantity ?: 0]) }}
                                    </p>
                                    @if ($this->unitConversionResult)
                                        <p class="mt-2 text-xs font-semibold text-[var(--brand-700)] dark:text-[var(--brand-300)]">{{ $this->unitConversionResult->displayString }}</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="setDefaultVariant('primary')" class="inline-flex items-center justify-center rounded-full border px-3 py-1.5 text-xs font-bold transition {{ $defaultVariantKey === 'primary' ? 'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' : 'border-stone-200 bg-white text-neutral-600 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-950 dark:text-zinc-300' }}">
                                    {{ $defaultVariantKey === 'primary' ? __('Default') : __('Make default') }}
                                </button>
                            </div>
                        </div>

                        @foreach ($additionalVariants as $index => $variant)
                            @php($variantUnit = ProductUnit::tryFrom($variant['unit'] ?? ''))
                            <div wire:key="edit-additional-variant-{{ $index }}-{{ $variant['id'] ?? 'new' }}" class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-950">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-neutral-900 dark:text-zinc-100">{{ __('Variant :number', ['number' => $loop->iteration + 1]) }}</p>
                                        <p class="mt-1 text-xs text-neutral-500 dark:text-zinc-400">{{ __('Each variant has its own price, stock, and optional conversion note.') }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="setDefaultVariant('variant-{{ $index }}')" class="inline-flex items-center justify-center rounded-full border px-3 py-1.5 text-xs font-bold transition {{ $defaultVariantKey === 'variant-'.$index ? 'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' : 'border-stone-200 bg-white text-neutral-600 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-300' }}">
                                            {{ $defaultVariantKey === 'variant-'.$index ? __('Default') : __('Make default') }}
                                        </button>
                                        <button type="button" wire:click="removeVariant({{ $index }})" wire:confirm="{{ __('Delete this variant?') }}" @disabled($defaultVariantKey === 'variant-'.$index) class="inline-flex items-center justify-center rounded-full border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-4 md:grid-cols-3 md:items-start">
                                    <flux:field>
                                        <flux:label>{{ __('Unit') }}</flux:label>
                                        <flux:select wire:model.live="additionalVariants.{{ $index }}.unit">
                                            @foreach (ProductUnit::cases() as $variantUnitOption)
                                                <flux:select.option :value="$variantUnitOption->value" :label="$variantUnitOption->label().' ('.$variantUnitOption->symbol().')'" />
                                            @endforeach
                                        </flux:select>
                                        <flux:error name="additionalVariants.{{ $index }}.unit" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Price') }}</flux:label>
                                        <flux:input.group>
                                            <flux:input.group.prefix>&#8369;</flux:input.group.prefix>
                                            <flux:input wire:model.live.debounce.250ms="additionalVariants.{{ $index }}.price" type="number" step="0.01" min="0.01" />
                                        </flux:input.group>
                                        <flux:error name="additionalVariants.{{ $index }}.price" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Stock') }}</flux:label>
                                        <flux:input.group>
                                            <flux:input wire:model.live.debounce.250ms="additionalVariants.{{ $index }}.stock_quantity" type="number" min="0" step="1" />
                                            @if ($variantUnit)
                                                <flux:input.group.suffix>{{ $variantUnit->symbol() }}</flux:input.group.suffix>
                                            @endif
                                        </flux:input.group>
                                        <flux:error name="additionalVariants.{{ $index }}.stock_quantity" />
                                    </flux:field>
                                </div>

                                <details class="mt-4 rounded-xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-zinc-900">
                                    <summary class="cursor-pointer text-sm font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Optional conversion note') }}</summary>
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('Conversion unit') }}</flux:label>
                                            <flux:select wire:model.live="additionalVariants.{{ $index }}.conversion_unit" placeholder="{{ __('None') }}">
                                                <flux:select.option value="" :label="__('None')" />
                                                @foreach (ProductUnit::cases() as $conversionUnitOption)
                                                    <flux:select.option :value="$conversionUnitOption->value" :label="$conversionUnitOption->label().' ('.$conversionUnitOption->symbol().')'" />
                                                @endforeach
                                            </flux:select>
                                            <flux:error name="additionalVariants.{{ $index }}.conversion_unit" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('Quantity per variant') }}</flux:label>
                                            <flux:input wire:model.live.debounce.250ms="additionalVariants.{{ $index }}.conversion_unit_quantity" type="number" min="0.001" max="99999" step="0.001" />
                                            <flux:error name="additionalVariants.{{ $index }}.conversion_unit_quantity" />
                                        </flux:field>
                                    </div>
                                </details>
                            </div>
                        @endforeach
                    </div>

                    <flux:error name="defaultVariantKey" />
                </section>

                <section class="space-y-5">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-eye text-[var(--brand-600)]"></i>
                        <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-neutral-900 dark:text-zinc-100">{{ __('Publishing') }}</h2>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button type="button" wire:click="$set('status', '{{ ProductStatus::Inactive->value }}')" @class([
                            'relative rounded-2xl bg-stone-100 p-4 text-left transition-all duration-200 active:scale-[0.97] dark:bg-zinc-900',
                            'border-2 border-[var(--brand-500)]' => $status === ProductStatus::Inactive->value,
                            'border' => $status !== ProductStatus::Inactive->value,
                            'border-stone-200 dark:border-white/10' => $status !== ProductStatus::Inactive->value,
                        ])>
                            @if ($status === ProductStatus::Inactive->value)
                                <span class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-[var(--brand-600)] text-white"><i class="fa-solid fa-check text-[10px]"></i></span>
                            @endif
                            <i class="fa-solid fa-lock text-neutral-500 dark:text-zinc-400"></i>
                            <p class="mt-3 text-sm font-bold text-neutral-900 dark:text-zinc-100">{{ __('Draft') }}</p>
                            <p class="mt-1 text-xs leading-5 text-neutral-500 dark:text-zinc-400">{{ __('Hidden from storefront. Only you can see it.') }}</p>
                        </button>

                        <button type="button" wire:click="$set('status', '{{ ProductStatus::Active->value }}')" @class([
                            'relative rounded-2xl p-4 text-left transition-all duration-200 active:scale-[0.97]',
                            'border-2 border-[var(--brand-500)] bg-[var(--brand-600)] text-white' => $status === ProductStatus::Active->value,
                            'border' => $status !== ProductStatus::Active->value,
                            'border-stone-200 bg-[var(--brand-50)] text-[var(--brand-800)] dark:border-[var(--brand-500)]/20 dark:bg-[var(--brand-500)]/10 dark:text-[var(--brand-200)]' => $status !== ProductStatus::Active->value,
                        ])>
                            @if ($status === ProductStatus::Active->value)
                                <span class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-white text-[var(--brand-600)]"><i class="fa-solid fa-check text-[10px]"></i></span>
                            @endif
                            <i class="fa-solid fa-globe"></i>
                            <p class="mt-3 text-sm font-bold">{{ __('Active') }}</p>
                            <p class="mt-1 text-xs leading-5 opacity-80">{{ __('Visible to shoppers on the storefront.') }}</p>
                        </button>
                    </div>

                    @if ($status === ProductStatus::Active->value && (int) $stock_quantity === 0)
                        <flux:callout icon="exclamation-triangle" variant="warning">
                            <flux:callout.text>{{ __('This product is active but has 0 stock - it will appear as sold out to customers.') }}</flux:callout.text>
                        </flux:callout>
                    @endif
                </section>

                <div class="space-y-3 pt-2">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update,productImageUpload" class="w-full justify-center py-4 text-base transition-all duration-150 active:scale-[0.97]">
                        <span wire:loading.remove wire:target="update">{{ __('Save product') }}</span>
                        <span wire:loading wire:target="update">{{ __('Saving product…') }}</span>
                    </flux:button>

                    <flux:modal.trigger name="delete-product-listing">
                        <button type="button" class="mx-auto block text-sm font-semibold text-neutral-400 transition hover:text-rose-600 dark:text-zinc-500 dark:hover:text-rose-300">
                            {{ __('Delete product') }}
                        </button>
                    </flux:modal.trigger>
                </div>
            </section>
        </div>
    </form>

    <livewire:pages::vendor.product-delete-modal :product-id="$productId" />
</div>

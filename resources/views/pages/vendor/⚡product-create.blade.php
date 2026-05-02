<?php

use App\Concerns\HasVendorGuard;
use App\Concerns\VendorProductValidationRules;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
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

    public $productImageUpload = null;

    public function mount(): void
    {
        $this->status = ProductStatus::Inactive->value;
    }

    public function save(): void
    {
        $validated = $this->validate($this->vendorProductRules());

        $imagePath = $this->productImageUpload->store('product-images', 'public');

        Product::query()->create([
            'vendor_id' => $this->approvedVendorProfile()->getKey(),
            'category_id' => (int) $validated['categoryId'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock_quantity' => (int) $validated['stock_quantity'],
            'image' => $imagePath,
            'status' => $validated['status'],
        ]);

        Flux::toast(variant: 'success', text: __('Product saved.'));

        $this->redirectRoute('vendor.products', navigate: true);
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

}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <a href="{{ route('vendor.products') }}" wire:navigate class="brand-hover-text inline-flex w-fit items-center gap-2 text-sm font-semibold text-neutral-500 dark:text-neutral-400">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Back to products') }}
        </a>
        <span class="brand-kicker">{{ __('New listing') }}</span>
        <h1 class="brand-serif text-3xl font-bold text-neutral-900 dark:text-neutral-100 sm:text-4xl">
            {{ __('Create a product for your stall') }}
        </h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-neutral-400">
            {{ __('Add a clear image, choose the right category, and decide whether the listing should launch live or stay as a draft for later.') }}
        </p>
    </section>

    <form
        wire:submit="save"
        class="brand-panel grid gap-8 rounded-[2rem] p-6 sm:p-8 lg:grid-cols-[minmax(16rem,0.45fr)_minmax(0,1fr)]"
        x-data="{
            previewUrl: null,
            dragOver: false,
            handleFile(event) {
                const file = event.target.files[0];

                if (! file) {
                    return;
                }

                if (this.previewUrl) {
                    URL.revokeObjectURL(this.previewUrl);
                }

                this.previewUrl = URL.createObjectURL(file);
            },
            setDroppedFile(event) {
                const files = event.dataTransfer.files;

                if (! files.length) {
                    return;
                }

                this.$refs.productImageInput.files = files;
                this.handleFile({ target: this.$refs.productImageInput });
                this.dragOver = false;
                this.$refs.productImageInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }"
    >
        <section class="space-y-3 lg:sticky lg:top-24 lg:self-start">
            <div
                class="min-h-64 rounded-[2rem] border-2 border-dashed border-stone-300 p-4 transition hover:bg-stone-50 dark:border-white/20 dark:hover:bg-white/5"
                x-bind:class="dragOver ? 'border-[var(--brand-400)] bg-[var(--brand-50)] dark:bg-white/5' : ''"
                x-on:dragover.prevent="dragOver = true"
                x-on:dragleave.prevent="dragOver = false"
                x-on:drop.prevent="setDroppedFile($event)"
            >
                <input
                    x-ref="productImageInput"
                    id="product-image-upload"
                    type="file"
                    wire:model="productImageUpload"
                    x-on:change="handleFile($event)"
                    accept="image/*"
                    class="sr-only"
                >

                <template x-if="previewUrl">
                    <div class="relative overflow-hidden rounded-[1.5rem]">
                        <img
                            x-bind:src="previewUrl"
                            alt="{{ __('Product preview') }}"
                            class="aspect-square w-full object-cover"
                        >
                        <label for="product-image-upload" class="absolute inset-x-4 bottom-4 cursor-pointer rounded-xl bg-white/90 px-4 py-3 text-center text-sm font-semibold text-neutral-800 shadow-sm backdrop-blur transition hover:bg-white dark:bg-neutral-950/85 dark:text-white">
                            {{ __('Change image') }}
                        </label>
                    </div>
                </template>

                <template x-if="!previewUrl">
                    <label for="product-image-upload" class="flex h-full cursor-pointer flex-col items-center justify-center gap-4 text-center">
                        <span class="brand-soft-surface flex h-14 w-14 items-center justify-center rounded-2xl">
                            <i class="fa-solid fa-camera text-lg"></i>
                        </span>
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ __('Click or drag to upload') }}
                            </p>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Use a clean photo that matches what customers will receive from your stall.') }}
                            </p>
                        </div>
                    </label>
                </template>
            </div>

            @error('productImageUpload')
                <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </section>

        <section class="space-y-5">
            <flux:input wire:model="name" :label="__('Product name')" type="text" required />

            <flux:textarea wire:model="description" :label="__('Description')" rows="3" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="relative">
                    <span class="pointer-events-none absolute left-px top-[1.9rem] flex h-[2.55rem] w-11 items-center justify-center rounded-l-xl border border-stone-200 bg-stone-50 text-sm font-semibold text-neutral-500 dark:border-white/10 dark:bg-white/5 dark:text-neutral-400">&#8369;</span>
                    <flux:input
                        wire:model="price"
                        :label="__('Price')"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0.01"
                        class="pl-12"
                        required
                    />
                </div>

                <flux:input
                    wire:model="stock_quantity"
                    :label="__('Stock quantity')"
                    type="number"
                    min="0"
                    step="1"
                    required
                />
            </div>

            <flux:select wire:model="categoryId" :label="__('Category')" placeholder="{{ __('Choose a category') }}">
                @foreach ($this->categoryGroups as $parentName => $categories)
                    <optgroup label="{{ $parentName }}">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </flux:select>

            <div class="space-y-3">
                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Publishing status') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        wire:click="$set('status', '{{ ProductStatus::Inactive->value }}')"
                        @class([
                            'rounded-xl border px-4 py-3 text-left transition',
                            'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' => $status === ProductStatus::Inactive->value,
                            'border-stone-200 bg-stone-100 text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-white/5 dark:text-neutral-300' => $status !== ProductStatus::Inactive->value,
                        ])
                    >
                        <div>
                            <p class="text-sm font-semibold">{{ __('Draft') }}</p>
                            <p class="mt-1 text-xs opacity-80">{{ __('Hidden from storefront') }}</p>
                        </div>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('status', '{{ ProductStatus::Active->value }}')"
                        @class([
                            'rounded-xl border px-4 py-3 text-left transition',
                            'border-[var(--brand-600)] bg-[var(--brand-600)] text-white' => $status === ProductStatus::Active->value,
                            'border-stone-200 bg-stone-100 text-neutral-700 hover:border-[var(--brand-300)] dark:border-white/10 dark:bg-white/5 dark:text-neutral-300' => $status !== ProductStatus::Active->value,
                        ])
                    >
                        <div>
                            <p class="text-sm font-semibold">{{ __('Active') }}</p>
                            <p class="mt-1 text-xs opacity-80">{{ __('Visible to shoppers') }}</p>
                        </div>
                    </button>
                </div>

                <flux:callout icon="information-circle" heading="{{ __('Draft listings stay off the storefront until you publish them.') }}" />
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save,productImageUpload"
                class="brand-button-primary w-full"
            >
                <span wire:loading.remove wire:target="save">{{ __('Save product') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
            </button>
        </section>
    </form>
</div>

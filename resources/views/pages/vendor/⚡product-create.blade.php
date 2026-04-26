<?php

use App\Concerns\VendorProductValidationRules;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create product')] class extends Component {
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
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);
        }

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

    private function hasApprovedVendorProfile(): bool
    {
        return auth()->user()->vendorProfile?->status === VendorStatus::Approved;
    }

    private function approvedVendorProfile()
    {
        $vendorProfile = auth()->user()->vendorProfile;

        abort_if($vendorProfile === null || $vendorProfile->status !== VendorStatus::Approved, 403);

        return $vendorProfile;
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('New listing') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
            {{ __('Create a product for your stall') }}
        </h1>
        <p class="max-w-2xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Add a clear image, choose the right category, and decide whether the listing should launch live or stay as a draft for later.') }}
        </p>
    </section>

    <form
        wire:submit="save"
        class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
        x-data="{
            previewUrl: null,
            handleFile(event) {
                const file = event.target.files[0];

                if (! file) {
                    return;
                }

                if (this.previewUrl) {
                    URL.revokeObjectURL(this.previewUrl);
                }

                this.previewUrl = URL.createObjectURL(file);
            }
        }"
    >
        <section class="brand-panel p-6 sm:p-8">
            <div class="rounded-[1.75rem] border-2 border-dashed border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/60">
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
                    <div class="space-y-4">
                        <img
                            x-bind:src="previewUrl"
                            alt="{{ __('Product preview') }}"
                            class="aspect-[4/3] w-full rounded-[1.5rem] object-cover"
                        >

                        <label for="product-image-upload" class="brand-button-secondary w-full cursor-pointer">
                            {{ __('Choose a different image') }}
                        </label>
                    </div>
                </template>

                <template x-if="!previewUrl">
                    <label for="product-image-upload" class="flex cursor-pointer flex-col items-center justify-center gap-4 py-12 text-center">
                        <span class="brand-soft-surface flex h-14 w-14 items-center justify-center rounded-2xl">
                            <i class="fa-solid fa-camera text-lg"></i>
                        </span>
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                {{ __('Click to upload or drag here') }}
                            </p>
                            <p class="text-sm text-neutral-500 dark:text-zinc-400">
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

        <section class="brand-panel space-y-6 p-6 sm:p-8">
            <flux:input wire:model="name" :label="__('Product name')" type="text" required />

            <flux:textarea wire:model="description" :label="__('Description')" rows="4" required />

            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-[2.7rem] text-sm font-semibold text-neutral-500 dark:text-zinc-400">₱</span>
                <flux:input
                    wire:model="price"
                    :label="__('Price')"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0.01"
                    class="pl-8"
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

            <flux:select wire:model="categoryId" :label="__('Category')" placeholder="{{ __('Choose a category') }}">
                @foreach ($this->categoryGroups as $parentName => $categories)
                    <optgroup label="{{ $parentName }}">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </flux:select>

            <flux:radio.group
                wire:model="status"
                :label="__('Publishing status')"
                :description="__('Choose whether this listing should stay hidden as a draft or go live immediately.')"
                variant="cards"
                class="grid gap-3"
            >
                <flux:radio :value="ProductStatus::Inactive->value">{{ __('Save as draft (inactive)') }}</flux:radio>
                <flux:radio :value="ProductStatus::Active->value">{{ __('Publish immediately (active)') }}</flux:radio>
            </flux:radio.group>

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

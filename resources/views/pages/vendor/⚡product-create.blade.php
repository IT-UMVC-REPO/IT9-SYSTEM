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
        class="grid gap-8 rounded-2xl border border-zinc-800 bg-zinc-900 p-8 lg:grid-cols-[280px_minmax(0,1fr)]"
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
        <section class="space-y-3">
            <div class="h-64 rounded-2xl border-2 border-dashed border-zinc-700 bg-zinc-950/50 p-4">
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
                    <img
                        x-bind:src="previewUrl"
                        alt="{{ __('Product preview') }}"
                        class="h-full w-full rounded-xl object-cover"
                    >
                </template>

                <template x-if="!previewUrl">
                    <label for="product-image-upload" class="flex h-full cursor-pointer flex-col items-center justify-center gap-4 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-800 text-zinc-300">
                            <i class="fa-solid fa-camera text-lg"></i>
                        </span>
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-zinc-100">
                                {{ __('Click to upload or drag here') }}
                            </p>
                            <p class="text-sm text-zinc-400">
                                {{ __('Use a clean photo that matches what customers will receive from your stall.') }}
                            </p>
                        </div>
                    </label>
                </template>
            </div>

            <label
                x-show="previewUrl"
                x-cloak
                for="product-image-upload"
                class="brand-button-secondary w-full cursor-pointer"
            >
                {{ __('Choose a different image') }}
            </label>

            @error('productImageUpload')
                <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </section>

        <section class="space-y-4">
            <flux:input wire:model="name" :label="__('Product name')" type="text" required />

            <flux:textarea wire:model="description" :label="__('Description')" rows="3" required />

            <div class="grid gap-4 sm:grid-cols-2">
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
                <p class="text-sm font-medium text-zinc-100">{{ __('Publishing status') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-700 p-4 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-950/30">
                        <input type="radio" wire:model="status" name="status" value="{{ ProductStatus::Inactive->value }}" class="accent-emerald-500">
                        <div>
                            <p class="text-sm font-medium text-zinc-100">{{ __('Draft') }}</p>
                            <p class="text-xs text-zinc-400">{{ __('Hidden from storefront') }}</p>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-700 p-4 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-950/30">
                        <input type="radio" wire:model="status" name="status" value="{{ ProductStatus::Active->value }}" class="accent-emerald-500">
                        <div>
                            <p class="text-sm font-medium text-zinc-100">{{ __('Active') }}</p>
                            <p class="text-xs text-zinc-400">{{ __('Visible to shoppers') }}</p>
                        </div>
                    </label>
                </div>

                <flux:callout icon="information-circle" heading="{{ __('Draft listings stay off the storefront until you publish them.') }}" />
            </div>

            <flux:button
                variant="primary"
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save,productImageUpload"
                class="w-full justify-center"
            >
                <span wire:loading.remove wire:target="save">{{ __('Save product') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
            </flux:button>
        </section>
    </form>
</div>

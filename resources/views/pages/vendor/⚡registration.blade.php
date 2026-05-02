<?php

use App\Concerns\VendorProductValidationRules;
use App\Enums\NotificationType;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Events\NotificationCreated;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Product;
use App\Models\VendorProfile;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Vendor registration')] class extends Component {
    use VendorProductValidationRules;
    use WithFileUploads;

    public string $store_name = '';

    public string $store_description = '';

    public string $vendor_address = '';

    public $storeImageUpload = null;

    public ?int $vendorProfileId = null;

    public ?string $currentStoreImage = null;

    public bool $showReapplicationForm = false;

    /**
     * @var array<int, array{
     *     productId: int|null,
     *     name: string,
     *     description: string,
     *     price: string,
     *     stock_quantity: string,
     *     categoryId: string,
     *     status: string,
     *     currentImage: string|null,
     *     currentImageUrl: string|null
     * }>
     */
    public array $sampleProducts = [];

    /**
     * @var array<int, mixed>
     */
    public array $sampleProductUploads = [];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->effectiveMarketplaceRole() === UserRole::Admin) {
            abort(403);
        }

        if ($user->effectiveMarketplaceRole() === UserRole::Vendor) {
            $this->redirectRoute('vendor.dashboard', navigate: true);

            return;
        }

        $this->vendorProfileId = $user->vendorProfile?->getKey();
        $this->hydrateFormFromExistingProfile();
    }

    public function addSampleProduct(): void
    {
        $this->sampleProducts[] = $this->emptySampleProduct();
    }

    public function removeSampleProduct(int $index): void
    {
        if (! array_key_exists($index, $this->sampleProducts) || count($this->sampleProducts) === 1) {
            return;
        }

        array_splice($this->sampleProducts, $index, 1);

        if (array_key_exists($index, $this->sampleProductUploads)) {
            unset($this->sampleProductUploads[$index]);
            $this->sampleProductUploads = array_values($this->sampleProductUploads);
        }
    }

    public function submit(): void
    {
        $user = auth()->user();
        $vendorProfile = $this->currentVendorProfileRecord();

        if ($user->effectiveMarketplaceRole() === UserRole::Admin) {
            abort(403);
        }

        if ($vendorProfile?->status === VendorStatus::Approved) {
            $this->redirectRoute('vendor.dashboard', navigate: true);

            return;
        }

        if ($vendorProfile?->status === VendorStatus::Pending && ! $this->showReapplicationForm) {
            abort(403);
        }

        $validated = $this->validate($this->vendorRegistrationRules());
        $storedStoreImagePath = $this->storeImageUpload->store('store-images', 'public');

        DB::transaction(function () use ($user, $vendorProfile, $validated, $storedStoreImagePath): void {
            if ($vendorProfile !== null) {
                $oldImagePath = $vendorProfile->getRawOriginal('store_image');

                if (
                    filled($oldImagePath)
                    && ! Str::startsWith($oldImagePath, ['http://', 'https://', '//'])
                    && Storage::disk('public')->exists($oldImagePath)
                ) {
                    Storage::disk('public')->delete($oldImagePath);
                }

                $vendorProfile->forceFill([
                    'store_name' => $validated['store_name'],
                    'store_description' => $validated['store_description'],
                    'vendor_address' => blank($validated['vendor_address'] ?? null) ? null : $validated['vendor_address'],
                    'store_image' => $storedStoreImagePath,
                    'status' => VendorStatus::Pending,
                    'rejection_reason' => null,
                    'approved_at' => null,
                ])->save();
            } else {
                $vendorProfile = VendorProfile::query()->create([
                    'user_id' => $user->getKey(),
                    'store_name' => $validated['store_name'],
                    'store_description' => $validated['store_description'],
                    'vendor_address' => blank($validated['vendor_address'] ?? null) ? null : $validated['vendor_address'],
                    'store_image' => $storedStoreImagePath,
                    'status' => VendorStatus::Pending,
                    'rejection_reason' => null,
                    'approved_at' => null,
                ]);
            }

            $this->syncSampleProducts($vendorProfile, $validated['sampleProducts']);
            $this->vendorProfileId = $vendorProfile->getKey();
            $this->currentStoreImage = $storedStoreImagePath;
        });

        $this->storeImageUpload = null;
        $this->showReapplicationForm = false;

        $notification = Notification::query()->create([
            'user_id' => $user->getKey(),
            'type' => NotificationType::System,
            'title' => 'Application submitted',
            'message' => 'Your vendor application has been received and is under review.',
            'data' => [
                'route' => 'vendor.registration',
            ],
        ]);

        event(new NotificationCreated($notification));

        Flux::toast(variant: 'success', text: __('Application submitted.'));

        $this->redirectRoute('vendor.registration', navigate: true);
    }

    public function beginReapplication(): void
    {
        $vendorProfile = $this->currentVendorProfileRecord();

        if ($vendorProfile?->status !== VendorStatus::Rejected) {
            abort(403);
        }

        $this->showReapplicationForm = true;
        $this->resetErrorBag();
        $this->hydrateFormFromExistingProfile($vendorProfile);
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
    public function currentVendorProfile(): ?VendorProfile
    {
        return $this->currentVendorProfileRecord();
    }

    #[Computed]
    public function currentStoreImageUrl(): ?string
    {
        return $this->storageUrlFor($this->currentStoreImage);
    }

    private function currentVendorProfileRecord(): ?VendorProfile
    {
        if ($this->vendorProfileId === null) {
            return auth()->user()->vendorProfile()->first();
        }

        try {
            return VendorProfile::query()->findOrFail($this->vendorProfileId);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function vendorRegistrationRules(): array
    {
        $rules = [
            'store_name' => ['required', 'string', 'max:120'],
            'store_description' => ['required', 'string', 'max:800'],
            'vendor_address' => ['nullable', 'string', 'max:500'],
            'storeImageUpload' => ['required', 'image', 'max:3072'],
            'sampleProducts' => ['required', 'array', 'min:1'],
        ];

        foreach (array_keys($this->sampleProducts) as $index) {
            $rules["sampleProducts.{$index}.productId"] = ['nullable', 'integer'];
            $rules["sampleProducts.{$index}.currentImage"] = ['nullable', 'string'];
            $rules += $this->vendorProductRules(
                prefix: "sampleProducts.{$index}",
                imageField: "sampleProductUploads.{$index}",
                requireImage: blank($this->sampleProducts[$index]['currentImage'] ?? null),
            );
        }

        return $rules;
    }

    private function hydrateFormFromExistingProfile(?VendorProfile $vendorProfile = null): void
    {
        $vendorProfile ??= $this->currentVendorProfileRecord();

        if ($vendorProfile === null) {
            $this->sampleProducts = [$this->emptySampleProduct()];
            $this->sampleProductUploads = [];

            return;
        }

        $this->store_name = $vendorProfile->store_name;
        $this->store_description = $vendorProfile->store_description;
        $this->vendor_address = $vendorProfile->vendor_address ?? '';
        $this->currentStoreImage = $vendorProfile->getRawOriginal('store_image');
        $this->sampleProducts = $vendorProfile->products()
            ->with('category:id,name,parent_id')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => $this->sampleProductFormState($product))
            ->all();
        $this->sampleProductUploads = [];

        if ($this->sampleProducts === []) {
            $this->sampleProducts = [$this->emptySampleProduct()];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $validatedProducts
     */
    private function syncSampleProducts(VendorProfile $vendorProfile, array $validatedProducts): void
    {
        $existingProducts = $vendorProfile->products()->get()->keyBy('id');
        $retainedProductIds = [];

        foreach (array_values($validatedProducts) as $index => $validatedProduct) {
            $productId = isset($validatedProduct['productId']) ? (int) $validatedProduct['productId'] : null;
            $product = $productId !== null ? $existingProducts->get($productId) : null;
            $product ??= new Product();

            $attributes = [
                'vendor_id' => $vendorProfile->getKey(),
                'category_id' => (int) $validatedProduct['categoryId'],
                'name' => $validatedProduct['name'],
                'description' => $validatedProduct['description'],
                'price' => $validatedProduct['price'],
                'stock_quantity' => (int) $validatedProduct['stock_quantity'],
                'status' => ProductStatus::Inactive,
            ];

            $uploadedImage = $this->sampleProductUploads[$index] ?? null;

            if ($uploadedImage !== null) {
                if ($product->exists) {
                    $this->deleteStoredPublicAsset($product->getRawOriginal('image'));
                }

                $attributes['image'] = $uploadedImage->store('product-images', 'public');
            }

            $product->forceFill($attributes)->save();
            $retainedProductIds[] = $product->getKey();
        }

        foreach ($existingProducts->except($retainedProductIds) as $productToDelete) {
            $this->deleteStoredPublicAsset($productToDelete->getRawOriginal('image'));
            $productToDelete->delete();
        }

        $vendorProfile->unsetRelation('products');
        $this->hydrateFormFromExistingProfile($vendorProfile);
    }

    private function emptySampleProduct(): array
    {
        return [
            'productId' => null,
            'name' => '',
            'description' => '',
            'price' => '',
            'stock_quantity' => '0',
            'categoryId' => '',
            'status' => ProductStatus::Inactive->value,
            'currentImage' => null,
            'currentImageUrl' => null,
        ];
    }

    private function sampleProductFormState(Product $product): array
    {
        return [
            'productId' => $product->getKey(),
            'name' => $product->name,
            'description' => $product->description,
            'price' => (string) $product->price,
            'stock_quantity' => (string) $product->stock_quantity,
            'categoryId' => (string) $product->category_id,
            'status' => ProductStatus::Inactive->value,
            'currentImage' => $product->getRawOriginal('image'),
            'currentImageUrl' => $product->image_url,
        ];
    }

    private function storageUrlFor(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '//'])
            ? $path
            : Storage::disk('public')->url($path);
    }

    private function deleteStoredPublicAsset(?string $path): void
    {
        if (
            blank($path)
            || Str::startsWith($path, ['http://', 'https://', '//'])
            || ! Storage::disk('public')->exists($path)
        ) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    @if ($this->currentVendorProfile?->status === VendorStatus::Pending && ! $showReapplicationForm)
        <section class="brand-panel mx-auto max-w-3xl px-8 py-14 text-center">
            <span class="brand-kicker border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                {{ __('Under review') }}
            </span>

            <h1 class="brand-serif mt-6 text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ __('Your application is being reviewed') }}
            </h1>

            <p class="mt-4 text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('We have received your store profile for :store. The admin team is reviewing your application before opening your dashboard.', ['store' => $this->currentVendorProfile->store_name]) }}
            </p>

            <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-secondary mt-8">
                {{ __('Return to storefront') }}
            </a>
        </section>
    @elseif ($this->currentVendorProfile?->status === VendorStatus::Rejected && ! $showReapplicationForm)
        <section class="mx-auto grid max-w-4xl gap-6">
            <div class="rounded-[2rem] border border-rose-200 bg-rose-50/90 p-8 shadow-sm dark:border-rose-500/20 dark:bg-rose-500/10">
                <span class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-rose-700 dark:border-rose-500/30 dark:bg-zinc-900 dark:text-rose-300">
                    {{ __('Needs changes') }}
                </span>

                <h1 class="brand-serif mt-6 text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('Your application needs another pass') }}
                </h1>

                <p class="mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-rose-600 dark:text-rose-300">
                    {{ __('Feedback from review') }}
                </p>

                <p class="mt-3 text-base leading-8 text-neutral-600 dark:text-zinc-300">
                    {{ $this->currentVendorProfile->rejection_reason }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" wire:click="beginReapplication" class="brand-button-primary">
                        {{ __('Reapply') }}
                    </button>

                    <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-secondary">
                        {{ __('Return to storefront') }}
                    </a>
                </div>
            </div>
        </section>
    @else
        <section class="grid gap-8 xl:grid-cols-[minmax(0,1.1fr)_minmax(24rem,0.9fr)]">
            <div
                class="brand-panel p-6 sm:p-8"
                x-data="{
                    previewUrl: @js($this->currentStoreImageUrl),
                    dragOver: false,
                    handleFile(event) {
                        const file = event.target.files[0];

                        if (! file) {
                            return;
                        }

                        if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                            URL.revokeObjectURL(this.previewUrl);
                        }

                        this.previewUrl = URL.createObjectURL(file);
                    },
                    setDroppedFile(event) {
                        const files = event.dataTransfer.files;

                        if (! files.length) {
                            return;
                        }

                        this.$refs.storeImageInput.files = files;
                        this.handleFile({ target: this.$refs.storeImageInput });
                        this.dragOver = false;
                        this.$refs.storeImageInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }"
            >
                <div class="flex flex-col gap-3">
                    <span class="brand-kicker">{{ __('Vendor onboarding') }}</span>
                    <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ $showReapplicationForm ? __('Refresh your vendor application') : __('Open your stall on SukiMarket') }}
                    </h1>
                    <p class="text-base leading-8 text-neutral-500 dark:text-zinc-400">
                        {{ __('Tell us about your store, upload a market-facing cover image, and add sample products so the admin team can review what your stall plans to sell.') }}
                    </p>
                </div>

                <form wire:submit="submit" class="mt-8 space-y-8">
                    <div
                        class="rounded-[1.75rem] border-2 border-dashed border-stone-200 bg-stone-50/80 p-5 transition dark:border-white/10 dark:bg-zinc-800/60"
                        x-bind:class="dragOver ? 'border-[var(--brand-400)] bg-[color:oklch(from_var(--brand-50)_l_c_h_/_0.9)] dark:bg-zinc-800' : ''"
                        x-on:dragover.prevent="dragOver = true"
                        x-on:dragleave.prevent="dragOver = false"
                        x-on:drop.prevent="setDroppedFile($event)"
                    >
                        <input
                            x-ref="storeImageInput"
                            id="store-image-upload"
                            type="file"
                            wire:model="storeImageUpload"
                            x-on:change="handleFile($event)"
                            accept="image/*"
                            class="sr-only"
                        >

                        <template x-if="previewUrl">
                            <div class="space-y-4">
                                <img
                                    x-bind:src="previewUrl"
                                    alt="{{ __('Store preview') }}"
                                    class="aspect-[5/3] w-full rounded-[1.5rem] object-cover"
                                >

                                <label for="store-image-upload" class="brand-button-secondary w-full cursor-pointer">
                                    {{ __('Choose a different image') }}
                                </label>
                            </div>
                        </template>

                        <template x-if="!previewUrl">
                            <label for="store-image-upload" class="flex cursor-pointer flex-col items-center justify-center gap-4 py-10 text-center">
                                <span class="brand-soft-surface flex h-14 w-14 items-center justify-center rounded-2xl">
                                    <i class="fa-solid fa-image text-lg"></i>
                                </span>
                                <div class="space-y-2">
                                    <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                        {{ __('Click to upload or drag here') }}
                                    </p>
                                    <p class="text-sm text-neutral-500 dark:text-zinc-400">
                                        {{ __('Use a clear image of your stall or product spread. JPG, PNG, or GIF up to 3 MB.') }}
                                    </p>
                                </div>
                            </label>
                        </template>
                    </div>

                    @error('storeImageUpload')
                        <p class="text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror

                    <flux:input wire:model="store_name" :label="__('Store name')" type="text" required />

                    <flux:textarea
                        wire:model="store_description"
                        :label="__('Store description')"
                        rows="4"
                        :placeholder="__('Tell customers what your stall is known for, what you sell, and what makes your store feel dependable.')"
                        required
                    />

                    <div>
                        <flux:textarea
                            wire:model="vendor_address"
                            :label="__('Vendor stall address')"
                            rows="2"
                            :placeholder="__('Stall number, market name, barangay, city (optional)')"
                        />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-zinc-400">
                            {{ __('Your stall address is shown to customers on your storefront page. Leave blank to hide it.') }}
                        </p>
                    </div>

                    <section class="space-y-6 border-t border-stone-200 pt-8 dark:border-white/10">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="brand-kicker !mb-0">{{ __('Sample products') }}</p>
                                <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                                    {{ __('Show the admin what your stall plans to sell') }}
                                </h2>
                                <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                                    {{ __('Add at least one sample product. These stay inactive until your application is approved, but they give the review team a concrete picture of your catalog.') }}
                                </p>
                            </div>

                            <button type="button" wire:click="addSampleProduct" class="brand-button-secondary">
                                <i class="fa-solid fa-plus text-xs"></i>
                                {{ __('Add another sample product') }}
                            </button>
                        </div>

                        @error('sampleProducts')
                            <p class="text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror

                        <div class="space-y-6">
                            @foreach ($sampleProducts as $index => $sampleProduct)
                                <article
                                    wire:key="vendor-registration-sample-product-{{ $sampleProduct['productId'] ?? 'new-'.$index }}"
                                    class="brand-panel p-6 sm:p-8"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-500 dark:text-zinc-400">
                                                {{ __('Sample product #:number', ['number' => $loop->iteration]) }}
                                            </p>
                                        </div>

                                        @if (count($sampleProducts) > 1)
                                            <button
                                                type="button"
                                                wire:click="removeSampleProduct({{ $index }})"
                                                class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:border-rose-500/30 dark:hover:bg-rose-500/15"
                                            >
                                                <i class="fa-solid fa-trash text-xs"></i>
                                                {{ __('Remove') }}
                                            </button>
                                        @endif
                                    </div>

                                    <div class="mt-6 grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
                                        <div
                                            x-data="{
                                                previewUrl: @js($sampleProduct['currentImageUrl']),
                                                handleFile(event) {
                                                    const file = event.target.files[0];

                                                    if (! file) {
                                                        return;
                                                    }

                                                    if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                                                        URL.revokeObjectURL(this.previewUrl);
                                                    }

                                                    this.previewUrl = URL.createObjectURL(file);
                                                }
                                            }"
                                        >
                                            <input
                                                id="sample-product-image-{{ $index }}"
                                                type="file"
                                                wire:model="sampleProductUploads.{{ $index }}"
                                                x-on:change="handleFile($event)"
                                                accept="image/*"
                                                class="sr-only"
                                            >

                                            <template x-if="previewUrl">
                                                <div class="space-y-4">
                                                    <img
                                                        x-bind:src="previewUrl"
                                                        alt="{{ __('Sample product preview') }}"
                                                        class="h-64 w-full rounded-xl object-cover"
                                                    >

                                                    <label for="sample-product-image-{{ $index }}" class="brand-button-secondary w-full cursor-pointer">
                                                        {{ __('Choose a different image') }}
                                                    </label>
                                                </div>
                                            </template>

                                            <template x-if="!previewUrl">
                                                <label for="sample-product-image-{{ $index }}" class="flex h-64 cursor-pointer flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-stone-200 bg-stone-50/80 px-4 text-center transition hover:bg-stone-50 dark:border-white/10 dark:bg-zinc-800/60 dark:hover:bg-zinc-800/80">
                                                    <span class="brand-soft-surface flex h-12 w-12 items-center justify-center rounded-2xl">
                                                        <i class="fa-solid fa-camera text-sm"></i>
                                                    </span>
                                                    <div class="space-y-2">
                                                        <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                                            {{ __('Upload product image') }}
                                                        </p>
                                                        <p class="text-xs leading-6 text-neutral-500 dark:text-zinc-400">
                                                            {{ __('Use the kind of photo customers should expect to see later in your catalog.') }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </template>

                                            @error("sampleProductUploads.$index")
                                                <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="space-y-4">
                                            <flux:input
                                                name="sampleProducts.{{ $index }}.name"
                                                wire:model="sampleProducts.{{ $index }}.name"
                                                :label="__('Product name')"
                                                type="text"
                                                required
                                            />

                                            <flux:textarea
                                                name="sampleProducts.{{ $index }}.description"
                                                wire:model="sampleProducts.{{ $index }}.description"
                                                :label="__('Description')"
                                                rows="3"
                                                required
                                            />

                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div class="relative">
                                                    <span class="pointer-events-none absolute left-4 top-[2.7rem] text-sm font-semibold text-neutral-500 dark:text-zinc-400">₱</span>
                                                    <flux:input
                                                        name="sampleProducts.{{ $index }}.price"
                                                        wire:model="sampleProducts.{{ $index }}.price"
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
                                                    name="sampleProducts.{{ $index }}.stock_quantity"
                                                    wire:model="sampleProducts.{{ $index }}.stock_quantity"
                                                    :label="__('Stock quantity')"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    required
                                                />
                                            </div>

                                            <flux:select
                                                name="sampleProducts.{{ $index }}.categoryId"
                                                wire:model="sampleProducts.{{ $index }}.categoryId"
                                                :label="__('Category')"
                                                placeholder="{{ __('Choose a category') }}"
                                            >
                                                @foreach ($this->categoryGroups as $parentName => $categories)
                                                    <optgroup label="{{ $parentName }}">
                                                        @foreach ($categories as $category)
                                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </flux:select>

                                            <flux:callout icon="information-circle" heading="{{ __('This draft stays off the storefront until approval.') }}" />
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit,storeImageUpload,sampleProductUploads"
                        class="brand-button-primary w-full"
                    >
                        <span wire:loading.remove wire:target="submit">
                            {{ $showReapplicationForm ? __('Submit reapplication') : __('Submit application') }}
                        </span>
                        <span wire:loading wire:target="submit">{{ __('Submitting...') }}</span>
                    </button>
                </form>
            </div>

            <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                <div class="brand-panel-muted p-6 sm:p-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] brand-accent-text">
                        {{ __('What approved vendors unlock') }}
                    </p>

                    <div class="mt-5 space-y-5">
                        @foreach ([
                            ['icon' => 'fa-solid fa-boxes-stacked', 'title' => __('Product listing tools'), 'copy' => __('Publish your catalog, manage stock, and keep your stall ready for browsing.')],
                            ['icon' => 'fa-solid fa-bag-shopping', 'title' => __('Vendor order queue'), 'copy' => __('Receive customer orders in one place and move them through preparation and delivery.')],
                            ['icon' => 'fa-solid fa-chart-line', 'title' => __('Sales visibility'), 'copy' => __('Track orders, revenue, and the products that bring customers back to your stall.')],
                        ] as $benefit)
                            <div class="flex items-start gap-4 rounded-[1.5rem] border border-stone-200 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/80">
                                <span class="brand-soft-surface flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
                                    <i class="{{ $benefit['icon'] }}"></i>
                                </span>
                                <div>
                                    <h2 class="text-base font-semibold text-neutral-900 dark:text-zinc-100">{{ $benefit['title'] }}</h2>
                                    <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $benefit['copy'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="brand-panel p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                        {{ __('Before you submit') }}
                    </p>
                    <ul class="mt-4 space-y-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        <li>{{ __('Use a store name customers will recognize in the market.') }}</li>
                        <li>{{ __('Describe your stall clearly so shoppers know what to expect.') }}</li>
                        <li>{{ __('Upload an image that represents how your storefront looks today.') }}</li>
                        <li>{{ __('Add sample products that clearly represent the items you plan to list after approval.') }}</li>
                    </ul>
                </div>
            </aside>
        </section>
    @endif
</div>

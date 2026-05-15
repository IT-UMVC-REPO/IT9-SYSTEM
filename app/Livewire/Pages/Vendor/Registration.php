<?php

namespace App\Livewire\Pages\Vendor;

use App\Concerns\VendorProductValidationRules;
use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Enums\TagumCoordinate;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Events\NotificationCreated;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Product;
use App\Models\VendorProfile;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Vendor registration')]
class Registration extends Component
{
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
     *     unit: string,
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

        if ($user->effectiveMarketplaceRole() === UserRole::Rider) {
            $this->redirectRoute('rider.dashboard', navigate: true);

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

        if (in_array($user->effectiveMarketplaceRole(), [UserRole::Admin, UserRole::Rider], true)) {
            abort(403);
        }

        if ($vendorProfile?->status === VendorStatus::Approved) {
            $this->redirectRoute('vendor.dashboard', navigate: true);

            return;
        }

        if ($vendorProfile?->status === VendorStatus::Pending && ! $this->showReapplicationForm) {
            abort(403);
        }

        $validated = $this->validate(
            $this->vendorRegistrationRules(),
            $this->vendorRegistrationValidationMessages(),
        );
        $storedStoreImagePath = $this->storeImageUpload->store('store-images', 'public');
        $isReapplication = $vendorProfile !== null;

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
                    ...TagumCoordinate::random(),
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
                    ...TagumCoordinate::random(),
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

        $submittedProfile = $this->currentVendorProfileRecord();

        if ($submittedProfile !== null) {
            AuditLogger::log(
                $isReapplication ? AuditEvent::VendorApplicationReapplied : AuditEvent::VendorApplicationSubmitted,
                "{$user->name} submitted a vendor application for '{$submittedProfile->store_name}'.",
                $submittedProfile,
            );
        }

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

    /**
     * @return array<string, string>
     */
    private function vendorRegistrationValidationMessages(): array
    {
        $messages = [
            'storeImageUpload.required' => __('Upload a clear store cover image before submitting.'),
            'storeImageUpload.image' => __('Use a valid image file for the store cover.'),
            'storeImageUpload.max' => __('Store cover images must be 3 MB or smaller.'),
        ];

        foreach (array_keys($this->sampleProducts) as $index) {
            $imageField = "sampleProductUploads.{$index}";

            $messages += $this->vendorProductValidationMessages(
                prefix: "sampleProducts.{$index}",
                imageField: $imageField,
            );

            $messages["{$imageField}.required"] = __('Upload a photo for sample product #:number before submitting.', [
                'number' => $index + 1,
            ]);
            $messages["{$imageField}.image"] = __('Sample product #:number needs a valid image file.', [
                'number' => $index + 1,
            ]);
            $messages["{$imageField}.max"] = __('Sample product #:number image must be 3 MB or smaller.', [
                'number' => $index + 1,
            ]);
        }

        return $messages;
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
            $product ??= new Product;
            $unit = ProductUnit::from($validatedProduct['unit'] ?? ProductUnit::Piece->value);
            $stockQuantity = (int) $validatedProduct['stock_quantity'];

            $attributes = [
                'vendor_id' => $vendorProfile->getKey(),
                'category_id' => (int) $validatedProduct['categoryId'],
                'name' => $validatedProduct['name'],
                'description' => $validatedProduct['description'],
                'price' => $validatedProduct['price'],
                'stock_quantity' => $stockQuantity,
                'canonical_stock_unit' => $unit->baseUnit()?->value,
                'canonical_stock_quantity' => $unit->conversionFactor() === null ? null : $stockQuantity * $unit->conversionFactor(),
                'unit' => $unit->value,
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
            $product->unitVariants()->updateOrCreate(
                ['is_default' => true],
                [
                    'unit' => $unit->value,
                    'price' => $validatedProduct['price'],
                    'stock_quantity' => $stockQuantity,
                    'conversion_unit' => null,
                    'conversion_unit_quantity' => null,
                    'sort_order' => 0,
                ],
            );
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
            'unit' => ProductUnit::Piece->value,
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
            'unit' => $product->unit->value,
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

    public function render(): View
    {
        return view('pages::vendor.⚡registration');
    }
}

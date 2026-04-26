<?php

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\VendorProfile;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Vendor registration')] class extends Component {
    use WithFileUploads;

    public string $store_name = '';

    public string $store_description = '';

    public $storeImageUpload = null;

    public ?int $vendorProfileId = null;

    public ?string $currentStoreImage = null;

    public bool $showReapplicationForm = false;

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

        $validated = $this->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_description' => ['required', 'string', 'max:800'],
            'storeImageUpload' => ['required', 'image', 'max:3072'],
        ]);

        $storedImagePath = $this->storeImageUpload->store('store-images', 'public');

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
                'store_image' => $storedImagePath,
                'status' => VendorStatus::Pending,
                'rejection_reason' => null,
                'approved_at' => null,
            ])->save();
        } else {
            $vendorProfile = VendorProfile::query()->create([
                'user_id' => $user->getKey(),
                'store_name' => $validated['store_name'],
                'store_description' => $validated['store_description'],
                'store_image' => $storedImagePath,
                'status' => VendorStatus::Pending,
                'rejection_reason' => null,
                'approved_at' => null,
            ]);
        }

        $this->vendorProfileId = $vendorProfile->getKey();
        $this->currentStoreImage = $storedImagePath;
        $this->storeImageUpload = null;
        $this->showReapplicationForm = false;

        $notification = Notification::query()->create([
            'user_id' => $user->getKey(),
            'type' => NotificationType::System,
            'title' => 'Application submitted',
            'message' => 'Your vendor application has been received and is under review.',
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
    public function currentVendorProfile(): ?VendorProfile
    {
        return $this->currentVendorProfileRecord();
    }

    #[Computed]
    public function currentStoreImageUrl(): ?string
    {
        if (blank($this->currentStoreImage)) {
            return null;
        }

        return Str::startsWith($this->currentStoreImage, ['http://', 'https://', '//'])
            ? $this->currentStoreImage
            : Storage::disk('public')->url($this->currentStoreImage);
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

    private function hydrateFormFromExistingProfile(?VendorProfile $vendorProfile = null): void
    {
        $vendorProfile ??= $this->currentVendorProfileRecord();

        if ($vendorProfile === null) {
            return;
        }

        $this->store_name = $vendorProfile->store_name;
        $this->store_description = $vendorProfile->store_description;
        $this->currentStoreImage = $vendorProfile->getRawOriginal('store_image');
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
                        {{ __('Tell us about your store, upload a market-facing cover image, and we will review your application before you start listing products.') }}
                    </p>
                </div>

                <form wire:submit="submit" class="mt-8 space-y-6">
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

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit,storeImageUpload"
                        class="brand-button-primary w-full"
                    >
                        <span wire:loading.remove wire:target="submit">{{ __('Submit application') }}</span>
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
                    </ul>
                </div>
            </aside>
        </section>
    @endif
</div>

<?php

use App\Enums\AuditEvent;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PublicDiskUrl;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Rider registration')] class extends Component
{
    use WithFileUploads;

    public string $vehicle_type = 'motorcycle';

    public string $plate_number = '';

    public string $contact_number = '';

    public string $bio = '';

    public ?string $currentProfileImage = null;

    public $profilePhotoUpload = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->currentProfileImage = $user->profile_image;

        if ($user->effectiveMarketplaceRole() === UserRole::Admin) {
            abort(403);
        }

        if ($user->effectiveMarketplaceRole() === UserRole::Rider) {
            $this->redirectRoute('rider.dashboard', navigate: true);

            return;
        }

        if ($user->effectiveMarketplaceRole() === UserRole::Vendor) {
            $this->redirectRoute('vendor.dashboard', navigate: true);

            return;
        }

        $profile = $user->riderProfile;

        if ($profile !== null) {
            $this->vehicle_type = $profile->vehicle_type;
            $this->plate_number = $profile->plate_number ?? '';
            $this->contact_number = $profile->contact_number ?? '';
            $this->bio = $profile->bio ?? '';
        }
    }

    public function submit(): void
    {
        $user = auth()->user();

        abort_if(in_array($user->effectiveMarketplaceRole(), [UserRole::Admin, UserRole::Vendor, UserRole::Rider], true), 403);

        $validated = $this->validate([
            'vehicle_type' => ['required', 'in:motorcycle,bicycle,e-bike,e-scooter,tricycle,car,van'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'contact_number' => ['required', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:160'],
            'profilePhotoUpload' => ['nullable', 'image', 'max:2048'],
        ]);

        $profileImagePath = null;

        if ($this->profilePhotoUpload !== null) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $profileImagePath = $this->profilePhotoUpload->store('profile-images', 'public');
        }

        $profile = DB::transaction(function () use ($user, $validated, $profileImagePath): RiderProfile {
            if ($profileImagePath !== null) {
                $user->forceFill(['profile_image' => $profileImagePath])->save();
            }

            $profile = RiderProfile::query()->updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'vehicle_type' => $validated['vehicle_type'],
                    'plate_number' => blank($validated['plate_number'] ?? null) ? null : $validated['plate_number'],
                    'contact_number' => $validated['contact_number'],
                    'bio' => blank($validated['bio'] ?? null) ? null : $validated['bio'],
                    'status' => 'pending',
                    'is_available' => false,
                    'approved_at' => null,
                ],
            );

            User::query()
                ->where('role', UserRole::Admin->value)
                ->each(function (User $admin) use ($user): void {
                    $notification = Notification::query()->create([
                        'user_id' => $admin->getKey(),
                        'type' => NotificationType::System,
                        'title' => 'New rider application',
                        'message' => $user->name.' submitted a rider application for review.',
                        'data' => [
                            'route' => 'admin.riders',
                        ],
                    ]);

                    event(new NotificationCreated($notification));
                });

            return $profile;
        });

        AuditLogger::log(AuditEvent::RiderApplicationSubmitted, "{$user->name} submitted a rider application.", $profile);

        unset($this->currentRiderProfile);
        $this->currentProfileImage = $profileImagePath ?? $this->currentProfileImage;
        $this->profilePhotoUpload = null;

        Flux::toast(variant: 'success', text: __('Application submitted. Awaiting admin approval.'));

        $this->redirectRoute('rider.registration', navigate: true);
    }

    #[Computed]
    public function currentRiderProfile(): ?RiderProfile
    {
        return auth()->user()->riderProfile()->first();
    }

    public function profileImageUrl(?string $path): ?string
    {
        return PublicDiskUrl::nullable($path);
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    @if ($this->currentRiderProfile?->status === 'pending')
        <section class="brand-panel mx-auto max-w-3xl px-8 py-14 text-center">
            <span class="brand-kicker border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                {{ __('Under review') }}
            </span>

            <h1 class="brand-serif mt-6 text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                {{ __('Your rider application is being reviewed') }}
            </h1>

            <p class="mt-4 text-base leading-8 text-neutral-500 dark:text-zinc-400">
                {{ __('The admin team is checking your vehicle and contact details before opening your rider dashboard.') }}
            </p>

            <a href="{{ route('shop.home') }}" wire:navigate class="brand-button-secondary active:scale-[0.96] mt-8">
                {{ __('Return to storefront') }}
            </a>
        </section>
    @else
        <section class="grid gap-8 xl:grid-cols-[minmax(0,1.05fr)_minmax(22rem,0.95fr)]">
            <div class="brand-panel p-6 sm:p-8">
                <div class="flex flex-col gap-3">
                    <a href="{{ route('shop.home') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        {{ __('Return to storefront') }}
                    </a>
                    <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ __('Deliver with SukiMarket') }}
                    </h1>
                    <p class="text-base leading-8 text-neutral-500 dark:text-zinc-400">
                        {{ __('Tell us how you will handle deliveries so the admin team can approve your rider account.') }}
                    </p>
                </div>

                <form wire:submit="submit" class="mt-8 space-y-6">
                    <flux:select wire:model="vehicle_type" :label="__('Vehicle type')" required>
                        <flux:select.option value="motorcycle">{{ __('Motorcycle') }}</flux:select.option>
                        <flux:select.option value="bicycle">{{ __('Bicycle') }}</flux:select.option>
                        <flux:select.option value="e-bike">{{ __('E-bike') }}</flux:select.option>
                        <flux:select.option value="e-scooter">{{ __('E-scooter') }}</flux:select.option>
                        <flux:select.option value="tricycle">{{ __('Tricycle') }}</flux:select.option>
                        <flux:select.option value="car">{{ __('Car') }}</flux:select.option>
                        <flux:select.option value="van">{{ __('Van') }}</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="plate_number" :label="__('Plate number')" :placeholder="__('Optional for bicycles')" maxlength="20" />

                    <flux:input wire:model="contact_number" :label="__('Contact number')" type="tel" maxlength="20" required />

                    <div class="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-center">
                        <div class="overflow-hidden rounded-[1.25rem] border border-stone-200 bg-stone-100 dark:border-white/10 dark:bg-zinc-800">
                            @if ($profilePhotoUpload)
                                <img src="{{ $profilePhotoUpload->temporaryUrl() }}" alt="{{ __('Profile photo preview') }}" class="h-32 w-full object-cover">
                            @elseif ($this->profileImageUrl($currentProfileImage))
                                <img src="{{ $this->profileImageUrl($currentProfileImage) }}" alt="{{ __('Current profile photo') }}" class="h-32 w-full object-cover">
                            @else
                                <div class="flex h-32 items-center justify-center text-neutral-400 dark:text-zinc-500">
                                    <i class="fa-solid fa-user text-2xl"></i>
                                </div>
                            @endif
                        </div>

                        <div>
                            <flux:input type="file" wire:model="profilePhotoUpload" :label="__('Profile photo')" accept="image/*" />
                            <flux:error name="profilePhotoUpload" />
                        </div>
                    </div>

                    <flux:textarea wire:model="bio" :label="__('Bio / Short intro')" :placeholder="__('A quick note customers and admins can recognize')" maxlength="160" />
                    <flux:error name="bio" />

                    <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96]">
                        <span wire:loading.remove wire:target="submit">{{ __('Submit rider application') }}</span>
                        <span wire:loading wire:target="submit">{{ __('Submitting...') }}</span>
                    </button>
                </form>
            </div>

            <aside class="brand-panel-muted h-fit p-6 sm:p-8 xl:sticky xl:top-24">
                <p class="brand-kicker !mb-0">{{ __('Rider workspace') }}</p>
                <h2 class="brand-serif mt-3 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                    {{ __('What approved riders unlock') }}
                </h2>

                <div class="mt-6 space-y-4">
                    @foreach ([
                        ['icon' => 'fa-solid fa-motorcycle', 'title' => __('Claim ready orders'), 'copy' => __('Pick up prepared orders from approved vendors when you are available.')],
                        ['icon' => 'fa-solid fa-location-dot', 'title' => __('Share live location'), 'copy' => __('Keep customers updated with approximate rider tracking while deliveries are active.')],
                        ['icon' => 'fa-solid fa-circle-check', 'title' => __('Complete deliveries'), 'copy' => __('Move assigned orders through pickup, out for delivery, and delivered states.')],
                    ] as $benefit)
                        <div class="flex items-start gap-4 rounded-[1.5rem] border border-stone-200 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/80">
                            <span class="brand-soft-surface flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl">
                                <i class="{{ $benefit['icon'] }}"></i>
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-neutral-900 dark:text-zinc-100">{{ $benefit['title'] }}</h3>
                                <p class="mt-2 text-sm leading-7 text-neutral-500 dark:text-zinc-400">{{ $benefit['copy'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </aside>
        </section>
    @endif
</div>

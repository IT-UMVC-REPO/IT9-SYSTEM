<?php

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\VendorProfile;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor review')] class extends Component {
    public VendorProfile $vendorProfile;

    public string $rejection_reason = '';

    public function mount(VendorProfile $vendorProfile): void
    {
        $this->vendorProfile = $vendorProfile->load([
            'user:id,name,email,phone,address',
            'products' => fn ($query) => $query->with('category:id,name')->latest()->limit(12),
        ]);
    }

    public function approve(): void
    {
        abort_if($this->vendorProfile->status !== VendorStatus::Pending, 403);

        DB::transaction(function (): void {
            $this->vendorProfile->forceFill([
                'status' => VendorStatus::Approved,
                'approved_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->vendorProfile->user()->update([
                'role' => UserRole::Vendor,
            ]);

            $notification = Notification::query()->create([
                'user_id' => $this->vendorProfile->user_id,
                'type' => NotificationType::System,
                'title' => 'Your store was approved!',
                'message' => 'Your vendor application has been approved. You can now access your vendor dashboard and start listing products.',
            ]);

            event(new NotificationCreated($notification));
        });

        Flux::toast(variant: 'success', text: __('Vendor approved.'));

        $this->redirectRoute('admin.vendors', navigate: true);
    }

    public function reject(): void
    {
        abort_if($this->vendorProfile->status !== VendorStatus::Pending, 403);

        $validated = $this->validate([
            'rejection_reason' => ['required', 'string', 'min:10'],
        ]);

        DB::transaction(function () use ($validated): void {
            $this->vendorProfile->forceFill([
                'status' => VendorStatus::Rejected,
                'approved_at' => null,
                'rejection_reason' => $validated['rejection_reason'],
            ])->save();

            $notification = Notification::query()->create([
                'user_id' => $this->vendorProfile->user_id,
                'type' => NotificationType::System,
                'title' => 'Application not approved',
                'message' => 'Your vendor application was not approved. Reason: '.$validated['rejection_reason'].'. You may reapply after addressing the feedback.',
            ]);

            event(new NotificationCreated($notification));
        });

        Flux::toast(variant: 'warning', text: __('Application rejected.'));

        $this->redirectRoute('admin.vendors', navigate: true);
    }

    public function revokeApproval(): void
    {
        abort_if($this->vendorProfile->status !== VendorStatus::Approved, 403);

        DB::transaction(function (): void {
            $this->vendorProfile->forceFill([
                'status' => VendorStatus::Pending,
                'approved_at' => null,
            ])->save();

            $this->vendorProfile->user()->update([
                'role' => UserRole::Customer,
            ]);
        });

        Flux::toast(variant: 'warning', text: __('Vendor approval revoked.'));

        $this->redirectRoute('admin.vendors', navigate: true);
    }
}; ?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(24rem,0.8fr)]">
        <div class="space-y-6">
            <div class="brand-panel overflow-hidden p-6 sm:p-8">
                <div class="overflow-hidden rounded-[2rem] bg-stone-100 dark:bg-zinc-800">
                    <img
                        src="{{ $vendorProfile->store_image_url }}"
                        alt="{{ $vendorProfile->store_name }}"
                        class="aspect-[16/9] w-full object-cover"
                    >
                </div>

                <div class="mt-6 flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="brand-kicker">{{ __('Submitted') }}</span>
                        <span class="text-sm text-neutral-500 dark:text-zinc-400">{{ $vendorProfile->created_at->format('M j, Y g:i A') }}</span>
                    </div>

                    <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ $vendorProfile->store_name }}
                    </h1>

                    <p class="text-base leading-8 text-neutral-500 dark:text-zinc-400">
                        {{ $vendorProfile->store_description }}
                    </p>
                </div>
            </div>

            <div class="brand-panel p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                            {{ __('Store listings') }}
                        </p>
                        <h2 class="brand-serif mt-2 text-2xl font-bold text-neutral-900 dark:text-zinc-100">
                            {{ __('Current catalog') }}
                        </h2>
                    </div>

                    <span class="brand-badge">{{ $vendorProfile->products->count() }}</span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($vendorProfile->products as $product)
                        <article class="rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-4 dark:border-white/10 dark:bg-zinc-800/70">
                            <img
                                src="{{ $product->image_url }}"
                                alt="{{ $product->name }}"
                                class="aspect-[4/3] w-full rounded-[1.25rem] object-cover"
                            >
                            <h3 class="mt-4 font-semibold text-neutral-900 dark:text-zinc-100">{{ $product->name }}</h3>
                            <p class="mt-2 text-sm text-neutral-500 dark:text-zinc-400">{{ $product->category->name }}</p>
                            <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-zinc-100">₱{{ number_format((float) $product->price, 2) }}</p>
                        </article>
                    @empty
                        <div class="sm:col-span-2 xl:col-span-3 rounded-[1.5rem] border border-dashed border-stone-200 p-8 text-center text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                            {{ __('This vendor has no product listings yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="brand-panel h-fit p-6 xl:sticky xl:top-24">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-neutral-400 dark:text-zinc-500">
                {{ __('Decision panel') }}
            </p>

            <div class="mt-5 space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5 dark:border-white/10 dark:bg-zinc-800/70">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Owner') }}</p>
                    <p class="mt-2 font-semibold text-neutral-900 dark:text-zinc-100">{{ $vendorProfile->user->name }}</p>
                    <p class="text-sm text-neutral-500 dark:text-zinc-400">{{ $vendorProfile->user->email }}</p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Phone') }}</p>
                    <p class="mt-2 text-sm text-neutral-700 dark:text-zinc-300">{{ $vendorProfile->user->phone ?: __('Not provided') }}</p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Address') }}</p>
                    <p class="mt-2 text-sm text-neutral-700 dark:text-zinc-300">{{ $vendorProfile->user->address ?: __('Not provided') }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Current status') }}</p>
                    <span @class([
                        'mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]',
                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $vendorProfile->status === VendorStatus::Pending,
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $vendorProfile->status === VendorStatus::Approved,
                        'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $vendorProfile->status === VendorStatus::Rejected,
                    ])>
                        {{ $vendorProfile->status->value }}
                    </span>
                </div>

                @if ($vendorProfile->status === VendorStatus::Approved && $vendorProfile->approved_at !== null)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Approved at') }}</p>
                        <p class="mt-2 text-sm text-neutral-700 dark:text-zinc-300">{{ $vendorProfile->approved_at->format('M j, Y g:i A') }}</p>
                    </div>
                @endif

                @if ($vendorProfile->status === VendorStatus::Rejected && filled($vendorProfile->rejection_reason))
                    <div class="rounded-[1.5rem] border border-rose-200 bg-rose-50/80 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                        {{ $vendorProfile->rejection_reason }}
                    </div>
                @endif
            </div>

            @if ($vendorProfile->status === VendorStatus::Pending)
                <div class="mt-8 space-y-3">
                    <button type="button" wire:click="approve" class="brand-button-primary w-full">
                        {{ __('Approve') }}
                    </button>

                    <flux:modal.trigger name="reject-vendor-application">
                        <flux:button variant="danger" type="button">
                            {{ __('Reject') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @elseif ($vendorProfile->status === VendorStatus::Approved)
                <div class="mt-8">
                    <flux:button
                        variant="danger"
                        type="button"
                        wire:click="revokeApproval"
                        wire:confirm="{{ __('Revoke this vendor approval and send them back to pending review?') }}"
                    >
                        {{ __('Revoke approval') }}
                    </flux:button>
                </div>
            @endif
        </aside>
    </section>

    <flux:modal name="reject-vendor-application" class="max-w-lg">
        <form wire:submit="reject" class="space-y-6 rounded-[1.5rem] border border-stone-200 bg-white/95 p-6 shadow-xl dark:border-white/10 dark:bg-zinc-900/95">
            <div>
                <flux:heading size="lg">{{ __('Reject application') }}</flux:heading>
                <flux:subheading>
                    {{ __('Add a clear reason so the vendor knows what needs to be corrected before reapplying.') }}
                </flux:subheading>
            </div>

            <flux:textarea wire:model="rejection_reason" :label="__('Reason for rejection')" rows="4" required />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">
                    {{ __('Confirm rejection') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

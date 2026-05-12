<?php

use App\Enums\AuditEvent;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public int $reportedUserId;

    public string $reporterRole;

    public string $reportedRole;

    public ?int $orderId = null;

    public string $reason = '';

    public string $description = '';

    public $attachmentUpload = null;

    public function mount(int $reportedUserId, string $reporterRole, ?int $orderId = null): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(in_array($reporterRole, ['customer', 'vendor'], true), 404);

        $user = auth()->user()->loadMissing('vendorProfile');
        $reportedUser = User::query()->with('vendorProfile:id,user_id,status')->findOrFail($reportedUserId);

        abort_if($reportedUserId === $user->getKey(), 403);
        abort_if($user->effectiveMarketplaceRole()->value !== $reporterRole, 403);

        $reportedRole = $reportedUser->effectiveMarketplaceRole();

        if ($reporterRole === 'customer') {
            abort_if($reportedRole !== UserRole::Vendor, 403);
        } else {
            abort_if(!in_array($reportedRole, [UserRole::Customer, UserRole::Vendor], true), 403);
        }

        $this->reportedUserId = $reportedUserId;
        $this->reporterRole = $reporterRole;
        $this->reportedRole = $reportedRole->value;
        $this->orderId = $orderId;

        if ($orderId !== null) {
            abort_if($this->reporterRole === 'vendor' && $this->reportedRole !== 'customer', 403);
            $this->authorizeOrderContext($user, $reportedUserId, $reporterRole, $orderId);
        }
    }

    /**
     * @return array<int, ReportReason>
     */
    #[Computed]
    public function availableReasons(): array
    {
        return ReportReason::availableFor($this->reporterRole);
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)->only($this->availableReasons)],
            'description' => ['nullable', 'string', 'max:800'],
            'attachmentUpload' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extensions:jpg,jpeg,png,webp,gif,pdf,doc,docx'],
        ]);

        $hasRecentOpenReport = Report::query()
            ->where('reporter_id', auth()->id())
            ->where('reported_user_id', $this->reportedUserId)
            ->where('status', ReportStatus::Open)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        if ($hasRecentOpenReport) {
            Flux::toast(variant: 'warning', text: __('You have already submitted a recent report for this user.'));

            return;
        }

        $description = trim((string) ($validated['description'] ?? ''));
        $attachmentPath = $this->attachmentUpload?->store('report-attachments', 'public');

        try {
            $report = Report::query()->create([
                'reporter_id' => auth()->id(),
                'reported_user_id' => $this->reportedUserId,
                'order_id' => $this->orderId,
                'reporter_role' => $this->reporterRole,
                'reason' => ReportReason::from($validated['reason']),
                'description' => filled($description) ? $description : null,
                'attachment_path' => $attachmentPath,
            ]);
        } catch (\Throwable $exception) {
            if ($attachmentPath !== null) {
                Storage::disk('public')->delete($attachmentPath);
            }

            throw $exception;
        }

        AuditLogger::log(AuditEvent::ReportSubmitted, auth()->user()->name." submitted a report against user #{$this->reportedUserId}: {$validated['reason']}.", $report, auth()->id());

        $this->reset('reason', 'description', 'attachmentUpload');

        Flux::toast(variant: 'success', text: __('Your report has been submitted. The admin team will review it.'));

        $this->dispatch('report-submitted');

        Flux::modal('report-user')->close();
    }

    private function authorizeOrderContext(User $user, int $reportedUserId, string $reporterRole, int $orderId): void
    {
        $order = Order::query()->with('vendor:id,user_id')->select('id', 'customer_id', 'vendor_id')->findOrFail($orderId);

        if ($reporterRole === 'customer') {
            abort_if($order->customer_id !== $user->getKey(), 403);
            abort_if($order->vendor?->user_id !== $reportedUserId, 403);

            return;
        }

        abort_if($user->vendorProfile === null, 403);
        abort_if($order->vendor_id !== $user->vendorProfile->getKey(), 403);
        abort_if($order->customer_id !== $reportedUserId, 403);
    }
};
?>

<flux:modal name="report-user" scroll="body" :closable="false" class="max-w-2xl overflow-hidden">
    <form wire:submit="submit" class="relative flex flex-col rounded-[1.75rem] overflow-hidden">
        <flux:modal.close>
            <button type="button" 
                class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-stone-300/80 bg-white/90 text-stone-500 transition hover:bg-stone-100 hover:text-stone-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-500)] focus-visible:ring-offset-2 dark:border-white/10 dark:bg-zinc-900/90 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                aria-label="{{ __('Close report modal') }}">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </flux:modal.close>

        <div class="rounded-t-[1.75rem] px-6 py-6 pr-16 sm:px-7 sm:pr-20">
            <div class="suki-reveal flex items-start gap-4" style="transition-delay: 50ms">
                <span
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[var(--brand-50)] text-[var(--brand-700)] dark:bg-zinc-800 dark:text-[var(--brand-200)]">
                    <i class="fa-solid fa-flag text-lg"></i>
                </span>

                <div class="min-w-0">
                    <p class="brand-kicker !mb-0">{{ __('Safety review') }}</p>
                    <h2 class="brand-serif mt-3 text-3xl font-bold text-neutral-900 dark:text-zinc-100">
                        {{ $reportedRole === 'vendor' ? __('Report this vendor') : __('Report this customer') }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                        {{ __('Share the clearest reason, add any details that help with context, and attach evidence if you have it.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 sm:px-7 sm:py-7">
            <div class="space-y-5">
                <div class="suki-reveal space-y-2" style="transition-delay: 100ms">
                    <flux:select wire:model="reason" :label="__('Reason')"
                        :description="__('Choose the report category that best matches what happened.')"
                        :invalid="$errors->has('reason')" required>
                        <flux:select.option value="">{{ __('Select a reason') }}</flux:select.option>

                        @foreach ($this->availableReasons as $availableReason)
                            <flux:select.option :value="$availableReason->value" :label="$availableReason->label()" />
                        @endforeach
                    </flux:select>

                    @error('reason')
                        <p class="text-xs font-medium text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="suki-reveal space-y-2" style="transition-delay: 160ms">
                    <flux:textarea wire:model="description" :label="__('Description')"
                        :description="__('Optional, but helpful when the report needs order or message context.')"
                        :invalid="$errors->has('description')" rows="5"
                        :placeholder="__('Describe what happened, when it happened, and anything the admin team should check.')" />

                    @error('description')
                        <p class="text-xs font-medium text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div
                    class="suki-reveal rounded-[1.5rem] border border-dashed border-stone-300 bg-stone-50/80 p-4 transition-all duration-200 dark:border-white/10 dark:bg-zinc-900/70"
                    style="transition-delay: 220ms">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                                {{ __('Evidence attachment') }}</p>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                                {{ __('Optional image or document, up to 5MB. Accepted: JPG, PNG, WEBP, GIF, PDF, DOC, and DOCX.') }}
                            </p>
                        </div>

                        <label for="report-attachment"
                            class="brand-button-secondary active:scale-[0.96] inline-flex cursor-pointer items-center gap-2 text-sm">
                            <i class="fa-solid fa-paperclip text-xs"></i>
                            {{ __('Choose file') }}
                        </label>
                    </div>

                    <input id="report-attachment" type="file" wire:model="attachmentUpload"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx" class="sr-only">

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span wire:loading wire:target="attachmentUpload"
                            class="inline-flex items-center gap-2 rounded-full bg-[var(--brand-50)] px-3 py-1 text-xs font-medium text-[var(--brand-700)] dark:bg-zinc-800 dark:text-[var(--brand-200)]">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            {{ __('Uploading attachment...') }}
                        </span>

                        @if ($attachmentUpload)
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-medium text-neutral-700 shadow-sm dark:bg-zinc-800 dark:text-zinc-200">
                                <i class="fa-solid fa-file"></i>
                                <span
                                    class="max-w-[14rem] truncate">{{ $attachmentUpload->getClientOriginalName() }}</span>
                            </span>
                        @endif
                    </div>

                    @error('attachmentUpload')
                        <p class="mt-3 text-xs font-medium text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="border-t border-stone-200/80 px-6 py-5 dark:border-white/10 sm:px-7">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <button type="button" class="brand-button-secondary w-full transition-all duration-150 active:scale-[0.96] sm:w-auto">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>

                <button type="submit" wire:loading.attr="disabled" wire:target="submit,attachmentUpload"
                    class="brand-button-primary w-full transition-all duration-150 active:scale-[0.96] sm:w-auto">
                    <span wire:loading.remove wire:target="submit">{{ __('Submit report') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Submitting...') }}</span>
                </button>
            </div>
        </div>
    </form>
</flux:modal>

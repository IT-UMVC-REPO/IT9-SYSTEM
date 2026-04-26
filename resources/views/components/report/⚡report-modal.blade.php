<?php

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $reportedUserId;

    public string $reporterRole;

    public ?int $orderId = null;

    public string $reason = '';

    public string $description = '';

    public function mount(int $reportedUserId, string $reporterRole, ?int $orderId = null): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(in_array($reporterRole, ['customer', 'vendor'], true), 404);

        $user = auth()->user()->loadMissing('vendorProfile');
        $reportedUser = User::query()
            ->with('vendorProfile:id,user_id,status')
            ->findOrFail($reportedUserId);

        abort_if($reportedUserId === $user->getKey(), 403);
        abort_if($user->effectiveMarketplaceRole()->value !== $reporterRole, 403);

        $expectedReportedRole = $reporterRole === 'customer'
            ? UserRole::Vendor
            : UserRole::Customer;

        abort_if($reportedUser->effectiveMarketplaceRole() !== $expectedReportedRole, 403);

        $this->reportedUserId = $reportedUserId;
        $this->reporterRole = $reporterRole;
        $this->orderId = $orderId;

        if ($orderId !== null) {
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

        Report::query()->create([
            'reporter_id' => auth()->id(),
            'reported_user_id' => $this->reportedUserId,
            'order_id' => $this->orderId,
            'reporter_role' => $this->reporterRole,
            'reason' => ReportReason::from($validated['reason']),
            'description' => filled($description) ? $description : null,
        ]);

        $this->reset('reason', 'description');

        Flux::toast(variant: 'success', text: __('Your report has been submitted. The admin team will review it.'));

        $this->dispatch('report-submitted');

        Flux::modal('report-user')->close();
    }

    private function authorizeOrderContext(User $user, int $reportedUserId, string $reporterRole, int $orderId): void
    {
        $order = Order::query()
            ->with('vendor:id,user_id')
            ->select('id', 'customer_id', 'vendor_id')
            ->findOrFail($orderId);

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

<flux:modal name="report-user" class="max-w-lg">
    <form wire:submit="submit" class="space-y-6 rounded-[1.5rem] border border-stone-200 bg-white/95 p-6 shadow-xl dark:border-white/10 dark:bg-zinc-900/95">
        <div>
            <flux:heading size="lg">
                {{ $reporterRole === 'customer' ? __('Report this vendor') : __('Report this customer') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Share the most relevant reason so the admin team can review this report quickly.') }}
            </flux:subheading>
        </div>

        <flux:select wire:model="reason" :label="__('Reason')" required>
            <flux:select.option value="">{{ __('Select a reason') }}</flux:select.option>

            @foreach ($this->availableReasons as $availableReason)
                <flux:select.option :value="$availableReason->value" :label="$availableReason->label()" />
            @endforeach
        </flux:select>

        <flux:textarea
            wire:model="description"
            :label="__('Description')"
            rows="5"
            :placeholder="__('Describe what happened (optional but helpful).')"
        />

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:modal.close>
                <flux:button variant="filled" type="button">
                    {{ __('Cancel') }}
                </flux:button>
            </flux:modal.close>

            <flux:button type="submit">
                {{ __('Submit report') }}
            </flux:button>
        </div>
    </form>
</flux:modal>

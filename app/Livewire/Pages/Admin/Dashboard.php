<?php

namespace App\Livewire\Pages\Admin;

use App\Concerns\BuildsDailyChartSeries;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Admin dashboard')]
class Dashboard extends Component
{
    use BuildsDailyChartSeries;

    public function refreshDashboard(): void {}

    #[Computed]
    public function kpis(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $totalUsers = User::query()->count();
        $pendingApplications = VendorProfile::query()
            ->where('status', VendorStatus::Pending)
            ->count();
        $ordersToday = Order::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();
        $revenueToday = (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $userDelta = User::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count()
            - User::query()->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();

        $pendingDelta = VendorProfile::query()
            ->where('status', VendorStatus::Pending)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count()
            - VendorProfile::query()
                ->where('status', VendorStatus::Pending)
                ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                ->count();

        $ordersDelta = $ordersToday - Order::query()
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->count();

        $revenueDelta = $revenueToday - (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('amount');

        return [
            [
                'icon' => 'fa-solid fa-users',
                'label' => __('Total users'),
                'value' => number_format($totalUsers),
                'delta' => $this->signedCount($userDelta).' '.__('signups vs yesterday'),
                'delta_class' => $this->deltaClass($userDelta),
            ],
            [
                'icon' => 'fa-solid fa-store',
                'label' => __('Pending vendor applications'),
                'value' => number_format($pendingApplications),
                'delta' => $this->signedCount($pendingDelta).' '.__('submissions vs yesterday'),
                'delta_class' => $this->deltaClass($pendingDelta),
            ],
            [
                'icon' => 'fa-solid fa-basket-shopping',
                'label' => __('Orders today'),
                'value' => number_format($ordersToday),
                'delta' => $this->signedCount($ordersDelta).' '.__('vs yesterday'),
                'delta_class' => $this->deltaClass($ordersDelta),
            ],
            [
                'icon' => 'fa-solid fa-wallet',
                'label' => __('Revenue today'),
                'value' => $this->peso($revenueToday),
                'delta' => $this->signedPeso($revenueDelta).' '.__('vs yesterday'),
                'delta_class' => $this->deltaClass($revenueDelta),
            ],
        ];
    }

    #[Computed]
    public function pendingApprovals(): Collection
    {
        return VendorProfile::query()
            ->with('user:id,name')
            ->where('status', VendorStatus::Pending)
            ->latest('created_at')
            ->take(3)
            ->get();
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::query()
            ->with([
                'customer:id,name',
                'vendor:id,store_name',
            ])
            ->latest('created_at')
            ->take(3)
            ->get();
    }

    #[Computed]
    public function platformHealth(): array
    {
        return [
            'active_products' => Product::query()->where('status', ProductStatus::Active)->count(),
            'active_vendor_storefronts' => VendorProfile::query()->where('status', VendorStatus::Approved)->count(),
            'unread_messages' => Message::query()->where('is_read', false)->count(),
            'notifications_sent_today' => Notification::query()
                ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
        ];
    }

    #[Computed]
    public function revenueChartData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['amount', 'paid_at']);

        $chart = $this->buildDailySeries(
            $start,
            $end,
            $payments,
            fn (Payment $payment): ?CarbonInterface => $payment->paid_at,
            fn (Payment $payment): float => (float) $payment->amount,
        );

        return [
            ...$chart,
            'total' => round(array_sum($chart['series']), 2),
        ];
    }

    #[Computed]
    public function orderVolumeChartData(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();

        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $chart = $this->buildDailySeries(
            $start,
            $end,
            $orders,
            fn (Order $order): ?CarbonInterface => Carbon::parse($order->created_at),
            fn (): int => 1,
        );

        return [
            ...$chart,
            'series' => array_map(static fn ($value): int => (int) round($value), $chart['series']),
            'total' => $orders->count(),
        ];
    }

    #[Computed]
    public function vendorStatusChartData(): array
    {
        $counts = VendorProfile::query()
            ->get(['status'])
            ->countBy(fn (VendorProfile $vendorProfile): string => $vendorProfile->status->value);

        $series = [
            (int) ($counts[VendorStatus::Approved->value] ?? 0),
            (int) ($counts[VendorStatus::Pending->value] ?? 0),
            (int) ($counts[VendorStatus::Rejected->value] ?? 0),
        ];

        return [
            'labels' => ['Approved', 'Pending', 'Rejected'],
            'series' => $series,
            'colors' => ['#16a34a', '#d97706', '#dc2626'],
            'total' => array_sum($series),
        ];
    }

    #[Computed]
    public function userRegistrationChartData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $users = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $chart = $this->buildDailySeries(
            $start,
            $end,
            $users,
            fn (User $user): ?CarbonInterface => Carbon::parse($user->created_at),
            fn (): int => 1,
        );

        return [
            ...$chart,
            'series' => array_map(static fn ($value): int => (int) round($value), $chart['series']),
            'total' => $users->count(),
        ];
    }

    private function signedCount(int|float $value): string
    {
        $formatted = number_format(abs($value));

        return $value > 0
            ? '+'.$formatted
            : ($value < 0 ? '-'.$formatted : '0');
    }

    private function signedPeso(int|float $value): string
    {
        $formatted = $this->peso(abs($value));

        return $value > 0
            ? '+'.$formatted
            : ($value < 0 ? '-'.$formatted : $this->peso(0));
    }

    private function deltaClass(int|float $value): string
    {
        if ($value > 0) {
            return 'text-emerald-600 dark:text-emerald-300';
        }

        if ($value < 0) {
            return 'text-rose-600 dark:text-rose-300';
        }

        return 'text-neutral-500 dark:text-zinc-400';
    }

    private function peso(int|float $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
    }

    public function render(): View
    {
        return view('pages::admin.⚡dashboard');
    }
}

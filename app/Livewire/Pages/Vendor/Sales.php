<?php

namespace App\Livewire\Pages\Vendor;

use App\Concerns\BuildsDailyChartSeries;
use App\Concerns\HasVendorGuard;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\VendorProfile;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Vendor Sales')]
class Sales extends Component
{
    use BuildsDailyChartSeries;
    use HasVendorGuard;

    #[Url(except: 'month')]
    public string $period = 'month';

    public function updatedPeriod(): void
    {
        unset($this->summary);
        unset($this->topProducts);
        unset($this->dailyRevenue);
    }

    public function refreshSalesData(): void
    {
        unset($this->summary);
        unset($this->topProducts);
        unset($this->dailyRevenue);
    }

    #[Computed]
    public function vendorProfile(): VendorProfile
    {
        return $this->approvedVendorProfile();
    }

    /**
     * @return array{total_revenue: float, total_orders: int, average_order_value: float}
     */
    #[Computed]
    public function summary(): array
    {
        $ordersQuery = $this->deliveredOrdersQuery();
        $paymentsQuery = $this->paidPaymentsQuery();

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (float) (clone $paymentsQuery)->sum('amount');

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order_value' => $totalOrders > 0
                ? round($totalRevenue / $totalOrders, 2)
                : 0.0,
        ];
    }

    #[Computed]
    public function topProducts(): Collection
    {
        return OrderItem::query()
            ->whereHas('order', function ($query): void {
                $query
                    ->where('vendor_id', $this->vendorProfile->getKey())
                    ->where('order_status', OrderStatus::Delivered);

                if ($this->periodStart() !== null) {
                    $query->where('created_at', '>=', $this->periodStart());
                }
            })
            ->with('product:id,name,image,price')
            ->select([
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(quantity * unit_price) as total_revenue'),
            ])
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, float>, total: float}
     */
    #[Computed]
    public function dailyRevenue(): array
    {
        $start = $this->chartStart();
        $end = now()->endOfDay();

        $payments = $this->paidPaymentsQuery()
            ->whereBetween('paid_at', [$start, $end])
            ->get(['amount', 'paid_at']);

        $chart = $this->buildDailySeries(
            start: $start,
            end: $end,
            records: $payments,
            dateResolver: fn (Payment $payment): ?CarbonInterface => $payment->paid_at,
            valueResolver: fn (Payment $payment): float => (float) $payment->amount,
        );

        return [
            ...$chart,
            'total' => round(array_sum($chart['series']), 2),
        ];
    }

    private function deliveredOrdersQuery()
    {
        return Order::query()
            ->where('vendor_id', $this->vendorProfile->getKey())
            ->where('order_status', OrderStatus::Delivered)
            ->when(
                $this->periodStart() !== null,
                fn ($query) => $query->where('created_at', '>=', $this->periodStart()),
            );
    }

    private function paidPaymentsQuery()
    {
        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', function ($query): void {
                $query
                    ->where('vendor_id', $this->vendorProfile->getKey())
                    ->where('order_status', OrderStatus::Delivered);
            })
            ->when(
                $this->periodStart() !== null,
                fn ($query) => $query->where('paid_at', '>=', $this->periodStart()),
            );
    }

    private function periodStart(): ?CarbonInterface
    {
        return match ($this->period) {
            'week' => now()->subDays(6)->startOfDay(),
            'month' => now()->subDays(29)->startOfDay(),
            default => null,
        };
    }

    private function chartStart(): CarbonInterface
    {
        if ($this->periodStart() !== null) {
            return Carbon::instance($this->periodStart());
        }

        $firstPaidAt = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('order', function ($query): void {
                $query
                    ->where('vendor_id', $this->vendorProfile->getKey())
                    ->where('order_status', OrderStatus::Delivered);
            })
            ->orderBy('paid_at')
            ->value('paid_at');

        return $firstPaidAt !== null
            ? Carbon::parse($firstPaidAt)->startOfDay()
            : now()->subDays(29)->startOfDay();
    }

    private function peso(float|int $value): string
    {
        return sprintf("\u{20B1}%s", number_format((float) $value, 2));
    }

    public function render(): View
    {
        return view('pages::vendor.⚡sales');
    }
}

<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Events\NotificationCreated;
use App\Events\OrderStatusUpdated;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $orderId,
        public int $userId,
        public string $title,
        public string $message,
        public NotificationType $type = NotificationType::OrderUpdate,
        public bool $broadcastOrderStatus = false,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        $notification = Notification::query()->create([
            'user_id' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'data' => [
                'order_id' => $order->getKey(),
            ],
        ]);

        try {
            event(new NotificationCreated($notification));
        } catch (Throwable $exception) {
            Log::error('Failed to broadcast created notification.', [
                'notification_id' => $notification->getKey(),
                'order_id' => $order->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }

        if (! $this->broadcastOrderStatus) {
            return;
        }

        try {
            event(new OrderStatusUpdated($order->fresh()));
        } catch (Throwable $exception) {
            Log::error('Failed to broadcast order status update.', [
                'order_id' => $order->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

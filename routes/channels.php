<?php

use App\Enums\UserRole;
use App\Models\ConversationGroupMember;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('order.{orderId}', function ($user, int $orderId): bool {
    $order = Order::query()
        ->select(['id', 'customer_id', 'vendor_id'])
        ->with('vendor:id,user_id')
        ->find($orderId);

    if ($order === null) {
        return false;
    }

    return $order->customer_id === $user->id
        || $order->vendor?->user_id === $user->id;
});

Broadcast::channel('messaging.{conversationKey}', function ($user, string $conversationKey): bool {
    [$firstUserId, $secondUserId] = array_pad(
        array_map('intval', explode('-', $conversationKey, 2)),
        2,
        0,
    );

    return in_array($user->id, [$firstUserId, $secondUserId], true);
});

Broadcast::channel('calls.{userId}', function ($user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('group.{groupId}', function ($user, int $groupId): bool {
    return ConversationGroupMember::query()
        ->where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('notifications.{userId}', function ($user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin.audit', function ($user): bool {
    return $user->effectiveMarketplaceRole() === UserRole::Admin;
});

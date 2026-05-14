<?php

use App\Enums\UserRole;
use App\Models\ConversationGroupMember;
use App\Models\Order;
use App\Models\VideoCall;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('order.{orderId}', function ($user, int $orderId): bool {
    $order = Order::query()
        ->select(['id', 'customer_id', 'vendor_id', 'rider_id'])
        ->with('vendor:id,user_id')
        ->find($orderId);

    if ($order === null) {
        return false;
    }

    return $order->customer_id === $user->id
        || $order->vendor?->user_id === $user->id
        || $order->rider_id === $user->id;
});

Broadcast::channel('messaging.{conversationKey}', function ($user, string $conversationKey): bool {
    [$firstUserId, $secondUserId] = array_pad(
        array_map('intval', explode('-', $conversationKey, 2)),
        2,
        0,
    );

    return in_array($user->id, [$firstUserId, $secondUserId], true);
});

Broadcast::channel('presence.conversation.{conversationKey}', function ($user, string $conversationKey): array|false {
    [$firstUserId, $secondUserId] = array_pad(
        array_map('intval', explode('-', $conversationKey, 2)),
        2,
        0,
    );

    if (! in_array($user->id, [$firstUserId, $secondUserId], true)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'initials' => $user->initials(),
    ];
});

Broadcast::channel('calls.{userId}', function ($user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('call.{callId}', function ($user, int $callId): bool {
    $call = VideoCall::query()
        ->select(['id', 'caller_id', 'receiver_id', 'group_id', 'is_group_call'])
        ->find($callId);

    if ($call === null) {
        return false;
    }

    if (! $call->is_group_call) {
        return in_array((int) $user->id, [
            (int) $call->caller_id,
            (int) $call->receiver_id,
        ], true);
    }

    if ($call->group_id === null) {
        return false;
    }

    return ConversationGroupMember::query()
        ->where('group_id', $call->group_id)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('group.{groupId}', function ($user, int $groupId): bool {
    return ConversationGroupMember::query()
        ->where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('presence.group.{groupId}', function ($user, int $groupId): array|false {
    $isMember = ConversationGroupMember::query()
        ->where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->exists();

    if (! $isMember) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'initials' => $user->initials(),
    ];
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

<?php

namespace App\Services;

use App\Events\GroupMessageSent;
use App\Models\GroupMessage;
use Illuminate\Support\Facades\Log;

class GroupMessageService
{
    public static function dispatchSystemMessage(int $groupId, string $event, int $actorId, string $content): GroupMessage
    {
        $message = GroupMessage::query()->create([
            'group_id' => $groupId,
            'sender_id' => $actorId,
            'content' => $content,
            'is_system_message' => true,
            'system_event' => $event,
            'system_actor_id' => $actorId,
        ]);

        try {
            event(new GroupMessageSent($message));
        } catch (\Throwable $broadcastException) {
            Log::warning('GroupMessageSent system broadcast failed (Pusher may be unavailable): '.$broadcastException->getMessage());
        }

        return $message;
    }
}

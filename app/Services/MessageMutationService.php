<?php

namespace App\Services;

use App\Events\GroupMessageUpdated;
use App\Events\MessageThreadUpdated;
use App\Events\MessageUpdated;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\MessagePin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessageMutationService
{
    public function editDirect(User $actor, Message $message, string $content): Message
    {
        abort_unless($message->sender_id === $actor->getKey(), 403);
        abort_if($message->deleted_at !== null, 422);

        $message->forceFill([
            'content' => trim($content),
            'edited_at' => now(),
        ])->save();

        event(new MessageUpdated($message, 'edited'));
        event(MessageThreadUpdated::direct($message, 'message.edited'));

        return $message;
    }

    public function deleteDirect(User $actor, Message $message, bool $forEveryone = true): Message
    {
        abort_unless($message->sender_id === $actor->getKey(), 403);
        abort_if($forEveryone && $message->created_at?->lessThan(now()->subDay()), 403);

        $message = DB::transaction(function () use ($message, $forEveryone): Message {
            if ($forEveryone) {
                $message->forceFill([
                    'content' => __('This message was deleted'),
                    'deleted_for_everyone_at' => now(),
                ])->save();
            }

            $message->delete();

            return $message;
        });

        event(new MessageUpdated($message, 'deleted'));
        event(MessageThreadUpdated::direct($message, 'message.deleted'));

        return $message;
    }

    public function pinDirect(User $actor, Message $message): MessagePin
    {
        abort_unless(in_array($actor->getKey(), [$message->sender_id, $message->receiver_id], true), 403);

        [$firstUserId, $secondUserId] = [
            min($message->sender_id, $message->receiver_id),
            max($message->sender_id, $message->receiver_id),
        ];

        $pin = MessagePin::query()->firstOrCreate([
            'pinnable_type' => Message::class,
            'pinnable_id' => $message->getKey(),
        ], [
            'direct_user_one_id' => $firstUserId,
            'direct_user_two_id' => $secondUserId,
            'pinned_by' => $actor->getKey(),
            'pinned_at' => now(),
        ]);

        event(MessageThreadUpdated::direct($message, 'message.pinned', [
            'pin_id' => $pin->getKey(),
        ]));

        return $pin;
    }

    public function editGroup(User $actor, GroupMessage $message, string $content): GroupMessage
    {
        abort_unless($message->sender_id === $actor->getKey(), 403);
        abort_if($message->deleted_at !== null, 422);

        $message->forceFill([
            'content' => trim($content),
            'edited_at' => now(),
        ])->save();

        event(new GroupMessageUpdated($message, 'edited'));
        event(MessageThreadUpdated::group((int) $message->group_id, 'message.edited'));

        return $message;
    }

    public function deleteGroup(User $actor, GroupMessage $message, bool $forEveryone = true): GroupMessage
    {
        abort_unless($message->sender_id === $actor->getKey() || $this->isGroupAdmin($actor, (int) $message->group_id), 403);

        $message = DB::transaction(function () use ($message, $forEveryone): GroupMessage {
            if ($forEveryone) {
                $message->forceFill([
                    'content' => __('This message was deleted'),
                    'deleted_for_everyone_at' => now(),
                ])->save();
            }

            $message->delete();

            return $message;
        });

        event(new GroupMessageUpdated($message, 'deleted'));
        event(MessageThreadUpdated::group((int) $message->group_id, 'message.deleted'));

        return $message;
    }

    public function pinGroup(User $actor, GroupMessage $message): MessagePin
    {
        abort_unless($this->isGroupAdmin($actor, (int) $message->group_id), 403);

        $pin = MessagePin::query()->firstOrCreate([
            'pinnable_type' => GroupMessage::class,
            'pinnable_id' => $message->getKey(),
        ], [
            'conversation_group_id' => $message->group_id,
            'pinned_by' => $actor->getKey(),
            'pinned_at' => now(),
        ]);

        event(MessageThreadUpdated::group((int) $message->group_id, 'message.pinned', [
            'pin_id' => $pin->getKey(),
        ]));

        return $pin;
    }

    private function isGroupAdmin(User $actor, int $groupId): bool
    {
        return ConversationGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $actor->getKey())
            ->where('role', 'admin')
            ->exists();
    }
}

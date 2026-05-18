<?php

namespace App\Events;

use App\Models\ConversationGroup;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageThreadUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $channels, public string $action, public array $payload = []) {}

    public static function direct(Message $message, string $action, array $payload = []): self
    {
        $conversationKey = min($message->sender_id, $message->receiver_id)
            .'-'
            .max($message->sender_id, $message->receiver_id);

        return new self(['messaging.'.$conversationKey], $action, $payload + [
            'chat_type' => 'direct',
            'message_id' => $message->getKey(),
        ]);
    }

    public static function group(ConversationGroup|int $group, string $action, array $payload = []): self
    {
        $groupId = $group instanceof ConversationGroup ? $group->getKey() : $group;

        return new self(['group.'.$groupId], $action, $payload + [
            'chat_type' => 'group',
            'group_id' => $groupId,
        ]);
    }

    public function broadcastAs(): string
    {
        return 'MessageThreadUpdated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return collect($this->channels)
            ->map(fn (string $channel): PrivateChannel => new PrivateChannel($channel))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            ...$this->payload,
        ];
    }
}

<?php

namespace App\Services;

use App\Events\MessageThreadUpdated;
use App\Models\ChatParticipantState;
use App\Models\ConversationGroup;
use App\Models\User;
use Illuminate\Support\Carbon;

class ChatParticipantStateService
{
    public function stateForDirect(User $actor, User|int $directUser): ChatParticipantState
    {
        return ChatParticipantState::forDirect($actor, $directUser);
    }

    public function stateForGroup(User $actor, ConversationGroup|int $group): ChatParticipantState
    {
        return ChatParticipantState::forGroup($actor, $group);
    }

    public function togglePinned(ChatParticipantState $state): ChatParticipantState
    {
        $state->forceFill([
            'pinned_at' => $state->pinned_at === null ? now() : null,
        ])->save();

        $this->broadcastState($state, 'chat.pin_toggled');

        return $state;
    }

    public function archive(ChatParticipantState $state): ChatParticipantState
    {
        $state->forceFill(['archived_at' => now()])->save();
        $this->broadcastState($state, 'chat.archived');

        return $state;
    }

    public function delete(ChatParticipantState $state): void
    {
        $state->delete();
        $this->broadcastState($state, 'chat.deleted');
    }

    public function markUnread(ChatParticipantState $state): ChatParticipantState
    {
        $state->forceFill(['marked_unread_at' => now()])->save();
        $this->broadcastState($state, 'chat.marked_unread');

        return $state;
    }

    public function mute(ChatParticipantState $state, ?Carbon $until): ChatParticipantState
    {
        $state->forceFill(['muted_until' => $until])->save();
        $this->broadcastState($state, 'chat.muted');

        return $state;
    }

    public function label(ChatParticipantState $state, ?string $label): ChatParticipantState
    {
        $state->forceFill(['label' => filled($label) ? mb_substr($label, 0, 40) : null])->save();
        $this->broadcastState($state, 'chat.labeled');

        return $state;
    }

    private function broadcastState(ChatParticipantState $state, string $action): void
    {
        if ($state->chat_type === 'group' && $state->group_id !== null) {
            event(MessageThreadUpdated::group($state->group_id, $action, [
                'state_id' => $state->getKey(),
                'user_id' => $state->user_id,
            ]));
        }
    }
}

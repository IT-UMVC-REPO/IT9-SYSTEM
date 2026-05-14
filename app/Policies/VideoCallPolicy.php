<?php

namespace App\Policies;

use App\Models\ConversationGroupMember;
use App\Models\User;
use App\Models\VideoCall;

class VideoCallPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VideoCall $videoCall): bool
    {
        return $this->isParticipant($videoCall, $user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VideoCall $videoCall): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VideoCall $videoCall): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VideoCall $videoCall): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VideoCall $videoCall): bool
    {
        return false;
    }

    public function signal(User $user, VideoCall $videoCall): bool
    {
        return $this->isParticipant($videoCall, $user);
    }

    public function answer(User $user, VideoCall $videoCall): bool
    {
        if ($videoCall->is_group_call) {
            return $this->isGroupMember($videoCall, $user);
        }

        return (int) $videoCall->receiver_id === (int) $user->getKey();
    }

    public function decline(User $user, VideoCall $videoCall): bool
    {
        if ($videoCall->is_group_call) {
            return $this->isGroupMember($videoCall, $user);
        }

        return (int) $videoCall->receiver_id === (int) $user->getKey();
    }

    public function end(User $user, VideoCall $videoCall): bool
    {
        return $this->isParticipant($videoCall, $user);
    }

    private function isParticipant(VideoCall $videoCall, User $user): bool
    {
        if ($videoCall->is_group_call) {
            return $this->isGroupMember($videoCall, $user);
        }

        return in_array((int) $user->getKey(), [
            (int) $videoCall->caller_id,
            (int) $videoCall->receiver_id,
        ], true);
    }

    private function isGroupMember(VideoCall $videoCall, User $user): bool
    {
        if ($videoCall->group_id === null) {
            return false;
        }

        return ConversationGroupMember::query()
            ->where('group_id', $videoCall->group_id)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}

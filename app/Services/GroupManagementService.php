<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Events\MessageThreadUpdated;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupManagementService
{
    public function updateDetails(User $actor, ConversationGroup $group, array $attributes): ConversationGroup
    {
        $this->authorizeAdmin($actor, $group);

        $updates = [
            'name' => mb_substr((string) ($attributes['name'] ?? $group->name), 0, 120),
            'description' => filled($attributes['description'] ?? null) ? mb_substr((string) $attributes['description'], 0, 500) : null,
            'max_members' => $attributes['max_members'] ?? null,
            'approval_required' => (bool) ($attributes['approval_required'] ?? false),
        ];

        if (array_key_exists('avatar_path', $attributes)) {
            $updates['avatar_path'] = $attributes['avatar_path'];
        }

        $group->forceFill($updates)->save();

        $this->audit($actor, $group, 'updated group details', ['fields' => array_keys($attributes)]);
        event(MessageThreadUpdated::group($group, 'group.updated'));

        return $group;
    }

    public function setNickname(User $actor, ConversationGroup $group, int $memberUserId, ?string $nickname): ConversationGroupMember
    {
        $this->authorizeAdmin($actor, $group);

        $membership = $this->membership($group, $memberUserId);
        $membership->forceFill([
            'nickname' => filled($nickname) ? mb_substr((string) $nickname, 0, 80) : null,
        ])->save();

        event(MessageThreadUpdated::group($group, 'group.member_nickname_updated', [
            'member_user_id' => $memberUserId,
        ]));

        return $membership;
    }

    public function promote(User $actor, ConversationGroup $group, int $memberUserId): ConversationGroupMember
    {
        $this->authorizeAdmin($actor, $group);

        $membership = $this->membership($group, $memberUserId);
        $membership->forceFill(['role' => 'admin'])->save();

        $this->audit($actor, $group, 'promoted a group member', ['member_user_id' => $memberUserId]);
        event(MessageThreadUpdated::group($group, 'group.member_promoted', ['member_user_id' => $memberUserId]));

        return $membership;
    }

    public function demote(User $actor, ConversationGroup $group, int $memberUserId): ConversationGroupMember
    {
        $this->authorizeAdmin($actor, $group);
        abort_if((int) $group->owner_id === $memberUserId, 403);

        $membership = $this->membership($group, $memberUserId);
        $membership->forceFill(['role' => 'member'])->save();

        $this->audit($actor, $group, 'demoted a group admin', ['member_user_id' => $memberUserId]);
        event(MessageThreadUpdated::group($group, 'group.member_demoted', ['member_user_id' => $memberUserId]));

        return $membership;
    }

    public function remove(User $actor, ConversationGroup $group, int $memberUserId): void
    {
        $this->authorizeAdmin($actor, $group);
        abort_if((int) $group->owner_id === $memberUserId, 403);

        $this->membership($group, $memberUserId)->delete();

        $this->audit($actor, $group, 'removed a group member', ['member_user_id' => $memberUserId]);
        event(MessageThreadUpdated::group($group, 'group.member_removed', ['member_user_id' => $memberUserId]));
    }

    public function transferOwnership(User $actor, ConversationGroup $group, int $memberUserId): ConversationGroup
    {
        abort_unless($this->isOwner($actor, $group), 403);

        return DB::transaction(function () use ($actor, $group, $memberUserId): ConversationGroup {
            $membership = $this->membership($group, $memberUserId);
            $membership->forceFill(['role' => 'admin'])->save();
            $group->forceFill(['owner_id' => $memberUserId])->save();

            $this->audit($actor, $group, 'transferred group ownership', ['member_user_id' => $memberUserId]);
            event(MessageThreadUpdated::group($group, 'group.ownership_transferred', ['member_user_id' => $memberUserId]));

            return $group;
        });
    }

    public function leave(User $actor, ConversationGroup $group): void
    {
        DB::transaction(function () use ($actor, $group): void {
            $this->membership($group, $actor->getKey())->delete();

            $remainingAdmins = ConversationGroupMember::query()
                ->where('group_id', $group->getKey())
                ->where('role', 'admin')
                ->exists();

            if (! $remainingAdmins || $this->isOwner($actor, $group)) {
                $nextMember = ConversationGroupMember::query()
                    ->where('group_id', $group->getKey())
                    ->with('user:id,name')
                    ->orderBy('joined_at')
                    ->first();

                if ($nextMember !== null) {
                    $nextMember->forceFill(['role' => 'admin'])->save();
                    $group->forceFill(['owner_id' => $nextMember->user_id])->save();
                }
            }
        });

        event(MessageThreadUpdated::group($group, 'group.member_left', ['member_user_id' => $actor->getKey()]));
    }

    public function regenerateInvite(User $actor, ConversationGroup $group, ?int $expiresInMinutes, ?int $usageLimit): ConversationGroup
    {
        $this->authorizeAdmin($actor, $group);

        $group->forceFill([
            'invite_token' => Str::random(48),
            'invite_expires_at' => $expiresInMinutes !== null ? now()->addMinutes($expiresInMinutes) : null,
            'invite_usage_limit' => $usageLimit,
            'invite_uses' => 0,
        ])->save();

        event(MessageThreadUpdated::group($group, 'group.invite_regenerated'));

        return $group;
    }

    private function authorizeAdmin(User $actor, ConversationGroup $group): void
    {
        abort_unless($this->isOwner($actor, $group) || ConversationGroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $actor->getKey())
            ->where('role', 'admin')
            ->exists(), 403);
    }

    private function isOwner(User $actor, ConversationGroup $group): bool
    {
        return (int) ($group->owner_id ?? $group->created_by) === $actor->getKey();
    }

    private function membership(ConversationGroup $group, int $memberUserId): ConversationGroupMember
    {
        return ConversationGroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $memberUserId)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(User $actor, ConversationGroup $group, string $action, array $metadata = []): void
    {
        AuditLogger::log(
            AuditEvent::GroupMemberAdded,
            $actor->name.' '.$action.' in '.$group->displayName($actor->getKey()).'.',
            $group,
            $actor->getKey(),
            $metadata,
        );
    }
}

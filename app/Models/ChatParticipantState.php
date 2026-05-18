<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'chat_type',
    'direct_user_id',
    'group_id',
    'label',
    'pinned_at',
    'archived_at',
    'muted_until',
    'marked_unread_at',
])]
class ChatParticipantState extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pinned_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'muted_until' => 'immutable_datetime',
            'marked_unread_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public static function forDirect(User $user, User|int $directUser): self
    {
        $directUserId = $directUser instanceof User ? $directUser->getKey() : $directUser;

        return self::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'chat_type' => 'direct',
            'direct_user_id' => $directUserId,
            'group_id' => null,
        ]);
    }

    public static function forGroup(User $user, ConversationGroup|int $group): self
    {
        $groupId = $group instanceof ConversationGroup ? $group->getKey() : $group;

        return self::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'chat_type' => 'group',
            'direct_user_id' => null,
            'group_id' => $groupId,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function directUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direct_user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConversationGroup::class);
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'group_id',
    'user_id',
    'role',
    'nickname',
    'joined_at',
    'last_read_at',
    'muted_until',
    'archived_at',
    'pinned_at',
    'marked_unread_at',
])]
class ConversationGroupMember extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CREATED_AT = 'joined_at';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'last_read_at' => 'immutable_datetime',
            'muted_until' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'pinned_at' => 'immutable_datetime',
            'marked_unread_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $member): mixed => $member->forgetBroadcastMembershipCache());
        static::deleted(fn (self $member): mixed => $member->forgetBroadcastMembershipCache());
        static::restored(fn (self $member): mixed => $member->forgetBroadcastMembershipCache());
        static::forceDeleted(fn (self $member): mixed => $member->forgetBroadcastMembershipCache());
    }

    public function forgetBroadcastMembershipCache(): void
    {
        Cache::forget("broadcast:group-member:{$this->group_id}:{$this->user_id}");
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConversationGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

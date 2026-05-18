<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'created_by',
    'owner_id',
    'avatar_path',
    'description',
    'max_members',
    'invite_token',
    'invite_expires_at',
    'invite_usage_limit',
    'invite_uses',
    'approval_required',
])]
class ConversationGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invite_expires_at' => 'immutable_datetime',
            'approval_required' => 'bool',
            'max_members' => 'int',
            'invite_usage_limit' => 'int',
            'invite_uses' => 'int',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationGroupMember::class, 'group_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class, 'group_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(GroupMessage::class, 'group_id')->latestOfMany('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_group_members', 'group_id', 'user_id')
            ->withPivot(['role', 'nickname', 'joined_at', 'last_read_at', 'muted_until', 'archived_at', 'pinned_at', 'marked_unread_at']);
    }

    public function autoName(int $forUserId): string
    {
        $members = $this->relationLoaded('memberUsers')
            ? $this->memberUsers->reject(fn (User $user): bool => $user->getKey() === $forUserId)->pluck('name')
            : $this->memberUsers()->whereKeyNot($forUserId)->pluck('name');

        if ($members->isEmpty()) {
            return __('SukiMarket group');
        }

        $visibleNames = $members->take(3)->implode(', ');
        $remainingCount = $members->count() - 3;

        if ($remainingCount > 0) {
            return Str::limit($visibleNames.' +'.$remainingCount.' more', 120, '');
        }

        return Str::limit($visibleNames, 120, '');
    }

    public function displayName(int $forUserId): string
    {
        return filled($this->name) ? $this->name : $this->autoName($forUserId);
    }
}

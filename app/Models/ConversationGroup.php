<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'created_by',
    'avatar_path',
])]
class ConversationGroup extends Model
{
    use HasFactory;

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

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_group_members', 'group_id', 'user_id')
            ->withPivot(['role', 'joined_at', 'last_read_at']);
    }

    public function autoName(int $forUserId): string
    {
        $members = $this->relationLoaded('memberUsers')
            ? $this->memberUsers->reject(fn (User $user): bool => $user->getKey() === $forUserId)->pluck('name')
            : $this->memberUsers()->whereKeyNot($forUserId)->pluck('name');

        if ($members->isEmpty()) {
            return __('LocalPalengke group');
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

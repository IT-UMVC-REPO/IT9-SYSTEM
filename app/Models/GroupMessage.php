<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'group_id',
    'sender_id',
    'content',
    'reply_to_id',
    'is_system_message',
    'system_event',
    'system_actor_id',
    'edited_at',
    'deleted_for_everyone_at',
    'forwarded_from_type',
    'forwarded_from_id',
    'created_at',
])]
class GroupMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system_message' => 'bool',
            'created_at' => 'immutable_datetime',
            'edited_at' => 'immutable_datetime',
            'deleted_for_everyone_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConversationGroup::class, 'group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GroupMessageAttachment::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function systemActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'system_actor_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(GroupMessageReaction::class);
    }

    public function pins(): MorphMany
    {
        return $this->morphMany(MessagePin::class, 'pinnable');
    }

    public function timeAgo(): string
    {
        return $this->created_at?->diffForHumans() ?? __('just now');
    }
}

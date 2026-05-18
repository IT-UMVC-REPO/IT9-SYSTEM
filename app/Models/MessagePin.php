<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'pinnable_type',
    'pinnable_id',
    'conversation_group_id',
    'direct_user_one_id',
    'direct_user_two_id',
    'pinned_by',
    'pinned_at',
])]
class MessagePin extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pinned_at' => 'immutable_datetime',
        ];
    }

    public function pinnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConversationGroup::class, 'conversation_group_id');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}

<?php

namespace App\Models;

use App\Enums\VideoCallStatus;
use Database\Factories\VideoCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'caller_id',
    'receiver_id',
    'group_id',
    'is_group_call',
    'conversation_key',
    'status',
    'started_at',
    'ended_at',
    'created_at',
])]
class VideoCall extends Model
{
    /** @use HasFactory<VideoCallFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VideoCallStatus::class,
            'is_group_call' => 'bool',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ConversationGroup::class, 'group_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(VideoCallParticipant::class);
    }

    public static function conversationKeyFor(int $firstUserId, int $secondUserId): string
    {
        return min($firstUserId, $secondUserId).'-'.max($firstUserId, $secondUserId);
    }
}

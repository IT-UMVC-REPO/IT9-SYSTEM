<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'video_call_id',
    'user_id',
    'joined_at',
    'left_at',
])]
class VideoCallParticipant extends Model
{
    use HasFactory;

    public const CREATED_AT = 'joined_at';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
        ];
    }

    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

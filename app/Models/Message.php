<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable(['sender_id', 'receiver_id', 'order_id', 'content', 'is_read', 'created_at'])]
class Message extends Model
{
    public const UPDATED_AT = null;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_read' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'bool',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function timeAgo(): string
    {
        return $this->created_at?->diffForHumans() ?? __('just now');
    }

    public static function conversationKey(int $otherUserId): string
    {
        $userId = auth()->id();

        if ($userId === null) {
            throw new RuntimeException('An authenticated user is required to generate a conversation key.');
        }

        return min($userId, $otherUserId).'-'.max($userId, $otherUserId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use RuntimeException;

#[Fillable([
    'sender_id',
    'receiver_id',
    'order_id',
    'content',
    'attachment_path',
    'attachment_name',
    'attachment_mime',
    'attachment_size',
    'is_read',
    'created_at',
])]
class Message extends Model
{
    use HasFactory;

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
            'attachment_size' => 'int',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * @return Collection<int, MessageAttachment>
     */
    public function attachmentsForDisplay(): Collection
    {
        $attachments = $this->relationLoaded('attachments')
            ? $this->attachments
            : $this->attachments()->oldest('id')->get();

        if ($attachments->isNotEmpty()) {
            return $attachments;
        }

        $legacyPath = $this->getRawOriginal('attachment_path');

        if (! filled($legacyPath)) {
            return collect();
        }

        return collect([
            MessageAttachment::make([
                'message_id' => $this->getKey(),
                'path' => $legacyPath,
                'name' => $this->getRawOriginal('attachment_name') ?? __('Attachment'),
                'mime' => $this->getRawOriginal('attachment_mime') ?? 'application/octet-stream',
                'size' => (int) ($this->getRawOriginal('attachment_size') ?? 0),
                'created_at' => $this->created_at,
            ]),
        ]);
    }

    public function timeAgo(): string
    {
        return $this->created_at?->diffForHumans() ?? __('just now');
    }

    protected function attachmentPath(): Attribute
    {
        return Attribute::get(fn ($value): ?string => $value ?: $this->firstAttachment()?->path);
    }

    protected function attachmentName(): Attribute
    {
        return Attribute::get(fn ($value): ?string => $value ?: $this->firstAttachment()?->name);
    }

    protected function attachmentMime(): Attribute
    {
        return Attribute::get(fn ($value): ?string => $value ?: $this->firstAttachment()?->mime);
    }

    protected function attachmentSize(): Attribute
    {
        return Attribute::get(fn ($value): ?int => $value !== null ? (int) $value : $this->firstAttachment()?->size);
    }

    private function firstAttachment(): ?MessageAttachment
    {
        if ($this->relationLoaded('attachments')) {
            return $this->attachments->first();
        }

        if (! $this->exists) {
            return null;
        }

        return $this->attachments()->oldest('id')->first();
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

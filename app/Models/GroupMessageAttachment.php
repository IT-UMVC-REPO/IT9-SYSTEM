<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'group_message_id',
    'path',
    'name',
    'mime',
    'size',
    'created_at',
])]
class GroupMessageAttachment extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'int',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function groupMessage(): BelongsTo
    {
        return $this->belongsTo(GroupMessage::class);
    }
}

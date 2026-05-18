<?php

namespace App\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'attachment_path',
        'ip_address',
        'user_agent',
        'is_read',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'bool',
            'read_at' => 'datetime',
        ];
    }

    public function markAsRead(): void
    {
        $this->forceFill([
            'is_read' => true,
            'read_at' => now(),
        ])->save();
    }

    public function subjectLabel(): string
    {
        return str((string) $this->subject)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}

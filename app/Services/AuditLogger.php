<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Events\AuditLogCreated;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function log(
        AuditEvent $event,
        string $description,
        ?Model $auditable = null,
        ?int $userId = null,
        ?array $metadata = null,
    ): AuditLog {
        $userId ??= Auth::id();

        $entry = AuditLog::query()->create([
            'user_id' => $userId,
            'event' => $event->value,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);

        try {
            event(new AuditLogCreated($entry));
        } catch (\Throwable $exception) {
            Log::warning('AuditLogCreated broadcast failed: '.$exception->getMessage());
        }

        return $entry;
    }
}

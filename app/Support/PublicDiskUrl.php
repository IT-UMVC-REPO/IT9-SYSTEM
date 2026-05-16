<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicDiskUrl
{
    public static function nullable(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (self::driver() === 'local' && ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function withFallback(?string $path, string $fallback): string
    {
        return self::nullable($path) ?? $fallback;
    }

    private static function driver(): string
    {
        return config('filesystems.disks.public.driver', 'local');
    }
}

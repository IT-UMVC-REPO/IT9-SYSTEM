<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasStorageImage
{
    protected function resolvePublicImageUrl(?string $path, string $fallback): string
    {
        if (blank($path)) {
            return $fallback;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (($this->publicDiskDriver()) === 'local' && ! Storage::disk('public')->exists($path)) {
            return $fallback;
        }

        return Storage::disk('public')->url($path);
    }

    private function publicDiskDriver(): string
    {
        return config('filesystems.disks.public.driver', 'local');
    }
}

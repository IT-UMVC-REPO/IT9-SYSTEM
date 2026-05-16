<?php

namespace App\Concerns;

use App\Support\PublicDiskUrl;

trait HasStorageImage
{
    protected function resolvePublicImageUrl(?string $path, string $fallback): string
    {
        return PublicDiskUrl::withFallback($path, $fallback);
    }

    protected function resolveNullablePublicImageUrl(?string $path): ?string
    {
        return PublicDiskUrl::nullable($path);
    }
}

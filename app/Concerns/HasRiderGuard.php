<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\RiderProfile;

trait HasRiderGuard
{
    public function mountHasRiderGuard(): void
    {
        abort_unless(auth()->user()?->effectiveMarketplaceRole() === UserRole::Rider, 403);
    }

    protected function hasApprovedRiderProfile(): bool
    {
        return auth()->user()->riderProfile?->status === 'approved';
    }

    protected function approvedRiderProfile(): RiderProfile
    {
        $riderProfile = auth()->user()->riderProfile;

        abort_if($riderProfile === null || $riderProfile->status !== 'approved', 403);

        return $riderProfile;
    }
}

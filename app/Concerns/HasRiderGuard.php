<?php

namespace App\Concerns;

use App\Models\RiderProfile;

trait HasRiderGuard
{
    public function mountHasRiderGuard(): void
    {
        if (! $this->hasApprovedRiderProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);
        }
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

<?php

namespace App\Concerns;

use App\Enums\VendorStatus;
use App\Models\VendorProfile;

trait HasVendorGuard
{
    public function mountHasVendorGuard(): void
    {
        if (! $this->hasApprovedVendorProfile()) {
            $this->redirectRoute('customer.dashboard', navigate: true);
        }
    }

    protected function hasApprovedVendorProfile(): bool
    {
        return auth()->user()->vendorProfile?->status === VendorStatus::Approved;
    }

    protected function approvedVendorProfile(): VendorProfile
    {
        $vendorProfile = auth()->user()->vendorProfile;

        abort_if($vendorProfile === null || $vendorProfile->status !== VendorStatus::Approved, 403);

        return $vendorProfile;
    }
}

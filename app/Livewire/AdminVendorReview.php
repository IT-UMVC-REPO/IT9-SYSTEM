<?php

namespace App\Livewire;

use App\Models\VendorProfile;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use App\Enums\VendorStatus;
use Livewire\Component;

class AdminVendorReview extends Component
{
    // Remove the 'VendorProfile' type hint from the property to avoid the TypeError
    public $vendor; 
    public $rejection_reason = '';

    public function mount($vendor) 
    {
        // This line converts the ID string "16" into the actual Model object
        $this->vendor = $vendor instanceof VendorProfile 
            ? $vendor 
            : VendorProfile::with('user')->findOrFail($vendor);
    }

    // --- FOLLOWS #B3 (Approve Action) ---
    public function approve()
    {
        $this->vendor->update([
            'status' => VendorStatus::Approved,
            'approved_at' => now(),
        ]);

        // Update user role to vendor
        $this->vendor->user->update(['role' => 'vendor']);

        // Create Notification record for the vendor
        Notification::create([
            'user_id' => $this->vendor->user_id,
            'title'   => 'Application Approved', // Fixes QueryException
            'message' => 'Your application for ' . $this->vendor->store_name . ' has been approved!',
            'is_read' => false,
        ]);

        session()->flash('status', 'Vendor approved successfully.');
        return redirect()->route('admin.vendors');
    }

    // --- FOLLOWS #B4 (Reject Action) ---
    public function reject()
    {
        $this->validate([
            'rejection_reason' => 'required|min:10',
        ]);

        $this->vendor->update([
            'status' => 'Rejected',
            'rejection_reason' => $this->rejection_reason,
        ]);

        // Create Notification record for the vendor
        Notification::create([
            'user_id' => $this->vendor->user_id,
            'type'    => 'warning',
            'title'   => 'Application Rejected', // Added required field
            'message' => 'Your vendor application was rejected. Reason: ' . $this->rejection_reason,
            'is_read' => false,
        ]);

        session()->flash('status', 'Vendor application rejected.');
        return redirect()->route('admin.vendors');
    }

    public function render()
    {
        return view('livewire.admin-vendor-review');
    }
}   
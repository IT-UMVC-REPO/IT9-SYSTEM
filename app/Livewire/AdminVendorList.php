<?php

namespace App\Livewire;

use App\Models\VendorProfile;
use Livewire\Component;
use Livewire\WithPagination;

class AdminVendorList extends Component
{
    use WithPagination;

    public $statusFilter = 'Pending'; // Default tab

    public function setFilter($status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function render()
    {
        // Task B1: Paginated VendorProfile table tabbed by status
        $vendors = VendorProfile::where('status', $this->statusFilter)
            ->with('user')
            ->latest()
            ->paginate(10);

        return view('livewire.admin-vendor-list', [
            'vendors' => $vendors
        ]);
    }
}
<?php

namespace App\Livewire;

use App\Models\VendorProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class VendorRegistrationForm extends Component
{
    use WithFileUploads;

    public $store_name = '';
    public $store_description = '';
    public $store_image;

    // Task A3: Validation Rules
    protected $rules = [
        'store_name' => 'required|min:3|max:255',
        'store_description' => 'required|min:10',
        'store_image' => 'required|image|max:2048', 
    ];

    public function submit()
    {
        $this->validate();

        // Task A3: Prevent duplicate submission
        if (VendorProfile::where('user_id', Auth::id())->exists()) {
            session()->flash('error', 'You have already submitted an application.');
            return;
        }

        // Task A1: Upload to public disk
        $path = $this->store_image->store('vendor-stores', 'public');

        // Task A1: Create VendorProfile (Status defaults to Pending in your Model)
        VendorProfile::create([
            'user_id' => Auth::id(),
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'store_image' => $path,
        ]);

        // Task A2: Redirect to refresh the page and show the pending message
        return redirect()->route('vendor.registration');
    }

    public function render()
    {
        return view('livewire.vendor-registration-form');
    }
}
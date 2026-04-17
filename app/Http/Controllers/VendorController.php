<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'title' => 'Vendor Dashboard',
            'heading' => 'Vendor operations hub',
            'description' => 'Manage listings, watch incoming orders, and keep your storefront updated.',
            'highlights' => [
                ['label' => 'Listings', 'value' => 'Manage', 'description' => 'Update your products, pricing, and stock levels.'],
                ['label' => 'Orders', 'value' => 'Process', 'description' => 'Confirm and prepare new customer orders quickly.'],
                ['label' => 'Sales', 'value' => 'Review', 'description' => 'Track store activity and revenue at a glance.'],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'title' => 'Admin Dashboard',
            'heading' => 'Admin control center',
            'description' => 'Review vendors, monitor marketplace activity, and keep the platform healthy.',
            'highlights' => [
                ['label' => 'Vendor approvals', 'value' => 'Queue', 'description' => 'Approve or reject pending vendor applications.'],
                ['label' => 'User oversight', 'value' => 'Users', 'description' => 'Review customer and vendor account health.'],
                ['label' => 'Disputes', 'value' => 'Orders', 'description' => 'Track escalations that need admin attention.'],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'title' => 'Shop',
            'heading' => 'Customer storefront',
            'description' => 'Browse fresh market goods, check your orders, and stay close to your favorite vendors.',
            'highlights' => [
                ['label' => 'Products', 'value' => 'Browse', 'description' => 'Explore the latest items from your marketplace vendors.'],
                ['label' => 'Orders', 'value' => 'Track', 'description' => 'Follow order updates from checkout to delivery.'],
                ['label' => 'Suki vendors', 'value' => 'Follow', 'description' => 'Keep up with vendors you trust and buy from often.'],
            ],
        ]);
    }
}

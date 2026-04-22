<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        return view('pages.vendor.dashboard');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('pages.customer.dashboard');
    }
}

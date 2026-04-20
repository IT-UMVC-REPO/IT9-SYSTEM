<?php

namespace App\Http\Controllers;

use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class LandingPageController extends Controller
{
    public function index(): View
    {
        $featuredVendor = VendorProfile::query()
            ->approved()
            ->whereHas('products', fn (Builder $query): Builder => $query->active())
            ->withCount([
                'products as active_products_count' => fn (Builder $query): Builder => $query->active(),
            ])
            ->with([
                'user:id,name',
                'products' => fn ($query) => $query
                    ->active()
                    ->with('category:id,name')
                    ->latest()
                    ->limit(3),
            ])
            ->inRandomOrder()
            ->first();

        $featuredProducts = $featuredVendor?->products ?? collect();

        return view('welcome', [
            'featuredVendor' => $featuredVendor,
            'featuredProducts' => $featuredProducts,
        ]);
    }
}

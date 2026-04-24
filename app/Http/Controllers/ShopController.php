<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        $popularVendors = VendorProfile::query()
            ->approved()
            ->withCount(['products as active_products_count' => function ($q) {
                $q->where('status', ProductStatus::Active);
            }])
            ->with('user:id,name')
            ->having('active_products_count', '>', 0)
            ->orderByDesc('approved_at')
            ->limit(6)
            ->get();

        return view('pages.shop.home', [
            'popularVendors' => $popularVendors,
        ]);
    }

    public function show(Product $product): View
    {
        $product = Product::query()
            ->visibleToCustomers()
            ->findOrFail($product->getKey());

        return view('pages.shop.show', [
            'product' => $product,
        ]);
    }

    public function vendors(): View
    {
        return view('pages.shop.vendors', [
            'vendors' => collect(),
        ]);
    }

    public function vendor(VendorProfile $vendorProfile): View
    {
        abort_unless($vendorProfile->status === VendorStatus::Approved, 404);

        return view('pages.shop.vendor-detail', [
            'vendorProfile' => $vendorProfile,
        ]);
    }
}

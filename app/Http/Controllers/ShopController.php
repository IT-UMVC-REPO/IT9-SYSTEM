<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Favorite;
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

    public function vendor(VendorProfile $vendorProfile): View
    {
        abort_unless($vendorProfile->status === VendorStatus::Approved, 404);

        $vendorProfile->load([
            'user:id,name',
        ]);

        $products = Product::query()
            ->where('vendor_id', $vendorProfile->getKey())
            ->active()
            ->with('category:id,name')
            ->latest()
            ->paginate(12);

        $activeProductCount = Product::query()
            ->where('vendor_id', $vendorProfile->getKey())
            ->active()
            ->count();

        $isFavorited = auth()->check()
            ? Favorite::query()
                ->where('customer_id', auth()->id())
                ->where('vendor_id', $vendorProfile->getKey())
                ->exists()
            : false;

        return view('pages.shop.vendor-detail', [
            'vendorProfile' => $vendorProfile,
            'products' => $products,
            'activeProductCount' => $activeProductCount,
            'isFavorited' => $isFavorited,
        ]);
    }
}

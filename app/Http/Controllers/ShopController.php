<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorCustomerStar;
use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

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
            ->with(['category:id,name', 'vendor.user:id,name'])
            ->visibleToCustomers()
            ->findOrFail($product->getKey());

        $isOwnProduct = Auth::check()
            && Auth::user()->vendorProfile?->getKey() === $product->vendor_id;

        return view('pages.shop.show', [
            'product' => $product,
            'isOwnProduct' => $isOwnProduct,
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

        $isOwnStall = Auth::check()
            && Auth::user()->vendorProfile?->getKey() === $vendorProfile->getKey();

        $isFavorited = Auth::check()
            && ! $isOwnStall
            ? Favorite::query()
                ->where('customer_id', Auth::id())
                ->where('vendor_id', $vendorProfile->getKey())
                ->exists()
            : false;

        return view('pages.shop.vendor-detail', [
            'vendorProfile' => $vendorProfile,
            'products' => $products,
            'activeProductCount' => $activeProductCount,
            'isFavorited' => $isFavorited,
            'isOwnStall' => $isOwnStall,
        ]);
    }

    public function customer(User $user): View
    {
        $user->loadMissing('vendorProfile:id,user_id,status');
        abort_if($user->effectiveMarketplaceRole() !== UserRole::Customer, 404);

        $ordersPlacedCount = Order::query()
            ->where('customer_id', $user->getKey())
            ->count();

        /** @var User $viewer */
        $viewer = Auth::user()->loadMissing('vendorProfile:id,user_id,status');
        $viewerRole = $viewer->effectiveMarketplaceRole();
        $canVendorStar = $viewerRole === UserRole::Vendor;

        $sharedOrderCount = $canVendorStar
            ? Order::query()
                ->where('customer_id', $user->getKey())
                ->where('vendor_id', $viewer->vendorProfile?->getKey())
                ->count()
            : 0;

        $isStarredByVendor = $canVendorStar
            ? VendorCustomerStar::query()
                ->where('vendor_user_id', $viewer->getKey())
                ->where('customer_id', $user->getKey())
                ->exists()
            : false;

        return view('pages.shop.customer-detail', [
            'customer' => $user,
            'ordersPlacedCount' => $ordersPlacedCount,
            'sharedOrderCount' => $sharedOrderCount,
            'canVendorStar' => $canVendorStar,
            'isStarredByVendor' => $isStarredByVendor,
        ]);
    }
}

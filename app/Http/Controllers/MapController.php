<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MapController extends Controller
{
    public function vendors(): JsonResponse
    {
        $vendors = VendorProfile::query()
            ->approved()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->with('user:id,name,brand_color')
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->active(),
            ])
            ->select(['id', 'user_id', 'store_name', 'store_description', 'store_image', 'lat', 'lng', 'vendor_address'])
            ->get();

        $features = $vendors->map(fn (VendorProfile $vendor): array => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $vendor->lng, (float) $vendor->lat],
            ],
            'properties' => [
                'id' => $vendor->id,
                'user_id' => $vendor->user_id,
                'name' => $vendor->store_name,
                'description' => Str::limit((string) $vendor->store_description, 120),
                'image' => $vendor->store_image_url,
                'address' => $vendor->vendor_address,
                'profileUrl' => route('shop.vendors.show', $vendor),
                'color' => $vendor->user?->brand_color ?? '#059669',
                'active_products_count' => (int) $vendor->active_products_count,
            ],
        ]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_unless(
            $user->effectiveMarketplaceRole() === UserRole::Vendor
            || $user->effectiveMarketplaceRole() === UserRole::Admin,
            403,
        );

        $vendorProfile = $user->vendorProfile;

        if ($vendorProfile === null && $user->effectiveMarketplaceRole() !== UserRole::Admin) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        $query = User::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('role', UserRole::Customer->value)
            ->where('is_active', true);

        if ($user->effectiveMarketplaceRole() === UserRole::Vendor && $vendorProfile !== null) {
            $query->whereHas('orders', fn ($orderQuery) => $orderQuery->where('vendor_id', $vendorProfile->getKey()));
        }

        $customers = $query->select(['id', 'name', 'lat', 'lng', 'address'])->get();

        $features = $customers->map(fn (User $customer): array => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $customer->lng, (float) $customer->lat],
            ],
            'properties' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'address' => $customer->address,
                'profileUrl' => route('shop.customers.show', $customer),
            ],
        ]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $searchTerm = $request->string('search')->trim()->toString();
        $selectedCategoryId = $request->integer('category');
        $selectedCategory = $selectedCategoryId > 0
            ? Category::query()
                ->with([
                    'children:id,parent_id',
                    'parent:id,name',
                ])
                ->find($selectedCategoryId)
            : null;
        $categoryFilterIds = $selectedCategory === null
            ? collect()
            : $selectedCategory->children
                ->pluck('id')
                ->push($selectedCategory->getKey())
                ->unique()
                ->values();
        $visibleProducts = fn (Builder $query): Builder => $query
            ->active()
            ->whereHas('vendor', fn (Builder $builder): Builder => $builder->approved());
        $visibleChildCategories = function ($query) use ($visibleProducts): void {
            $query->whereHas('products', $visibleProducts);
        };

        $products = Product::query()
            ->visibleToCustomers()
            ->when(
                $selectedCategory !== null,
                fn (Builder $query): Builder => $query->whereIn('category_id', $categoryFilterIds),
            )
            ->search($searchTerm)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->parents()
            ->whereHas('children', $visibleChildCategories)
            ->with(['children' => function ($query) use ($visibleChildCategories): void {
                $visibleChildCategories($query);

                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

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
            'categories' => $categories,
            'products' => $products,
            'searchTerm' => $searchTerm,
            'selectedCategory' => $selectedCategory?->id,
            'selectedCategoryName' => $selectedCategory?->name,
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $searchTerm = $request->string('search')->trim()->toString();
        $selectedCategory = $request->integer('category');

        $products = Product::query()
            ->visibleToCustomers()
            ->when(
                $selectedCategory > 0,
                fn (Builder $query): Builder => $query->where('category_id', $selectedCategory),
            )
            ->search($searchTerm)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->whereHas('products', function (Builder $query): void {
                $query
                    ->active()
                    ->whereHas('vendor', fn (Builder $builder): Builder => $builder->approved());
            })
            ->withCount(['products' => function (Builder $query): void {
                $query
                    ->active()
                    ->whereHas('vendor', fn (Builder $builder): Builder => $builder->approved());
            }])
            ->orderBy('name')
            ->get();

        return view('pages.shop.home', [
            'categories' => $categories,
            'products' => $products,
            'searchTerm' => $searchTerm,
            'selectedCategory' => $selectedCategory > 0 ? $selectedCategory : null,
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

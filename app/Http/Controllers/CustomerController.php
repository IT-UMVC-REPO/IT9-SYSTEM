<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount(['products' => function ($query) {
            $query->where('status', ProductStatus::Active);
        }])->get();

        $featuredProducts = Product::with(['vendor', 'category'])
            ->where('status', ProductStatus::Active)
            ->where('stock_quantity', '>', 0)
            ->latest()
            ->take(8)
            ->get();

        return view('pages.shop.home', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }
}

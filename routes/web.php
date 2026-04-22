<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VendorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    // Shared portal entry point that forwards users to their role-specific home route.
    Route::get('dashboard', function (Request $request) {
        return redirect()->route($request->user()->homeRoute(), $request->query());
    })->name('dashboard');
});

Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [CustomerController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:customer'])->prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('home');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');
    Route::view('/cart', 'pages.shop.cart')->name('cart');
    Route::view('/checkout', 'pages.shop.checkout')->name('checkout');
    Route::view('/orders', 'pages.shop.orders')->name('orders');
    Route::view('/orders/{orderReference}', 'pages.shop.order-detail')->name('orders.show');
    Route::view('/favorites', 'pages.shop.favorites')->name('favorites');
});

Route::middleware(['auth', 'role:customer'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::view('/register', 'pages.vendor.registration')->name('registration');
});

Route::middleware(['auth', 'role:customer,vendor'])->prefix('messages')->name('messages.')->group(function () {
    Route::view('/', 'pages.messages.inbox')->name('inbox');
    Route::view('/{conversationReference}', 'pages.messages.conversation')->name('conversation');
});

Route::middleware(['auth', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/dashboard', [VendorController::class, 'index'])->name('dashboard');
    Route::view('/products', 'pages.vendor.products')->name('products');
    Route::view('/products/create', 'pages.vendor.product-create')->name('products.create');
    Route::view('/products/{productReference}/edit', 'pages.vendor.product-edit')->name('products.edit');
    Route::view('/orders', 'pages.vendor.orders')->name('orders');
    Route::view('/orders/{orderReference}', 'pages.vendor.order-detail')->name('orders.show');
    Route::view('/sales', 'pages.vendor.sales')->name('sales');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::view('/vendors', 'pages.admin.vendors')->name('vendors');
    Route::view('/vendors/{vendorReference}', 'pages.admin.vendor-detail')->name('vendors.show');
    Route::view('/users', 'pages.admin.users')->name('users');
    Route::view('/orders', 'pages.admin.orders')->name('orders');
});

require __DIR__.'/settings.php';

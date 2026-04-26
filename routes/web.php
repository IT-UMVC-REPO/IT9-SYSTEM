<?php

use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\ShopController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index'])->name('home');
Route::post('/webhooks/paymongo', [PayMongoWebhookController::class, 'handle'])->name('webhooks.paymongo');
Route::get('/shop/payment/success', [PaymentReturnController::class, 'success'])->name('shop.payment.success');
Route::get('/shop/payment/failed', [PaymentReturnController::class, 'failed'])->name('shop.payment.failed');

Route::middleware('auth')->group(function () {
    // Shared portal entry point that forwards users to their role-specific home route.
    Route::get('dashboard', function (Request $request) {
        return redirect()->route($request->user()->homeRoute(), $request->query());
    })->name('dashboard');
});

Route::middleware(['auth', 'role:customer,vendor'])->prefix('customer')->name('customer.')->group(function () {
    Route::livewire('/dashboard', 'pages::customer.dashboard')->name('dashboard');
});

Route::middleware(['auth', 'role:customer,vendor'])->prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('home');
    Route::livewire('/vendors', 'pages::shop.vendors')->name('vendors');
    Route::get('/vendors/{vendorProfile}', [ShopController::class, 'vendor'])->name('vendors.show');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');
    Route::livewire('/cart', 'pages::shop.cart')->name('cart');
    Route::livewire('/checkout', 'pages::shop.checkout')->name('checkout');
    Route::livewire('/orders', 'pages::shop.orders')->name('orders');
    Route::livewire('/orders/{orderReference}', 'pages::shop.order-detail')->name('orders.show');
    Route::livewire('/favorites', 'pages::shop.favorites')->name('favorites');
});

Route::middleware(['auth'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::livewire('/register', 'pages::vendor.registration')->name('registration');
});

Route::middleware(['auth', 'role:customer,vendor'])->prefix('messages')->name('messages.')->group(function () {
    Route::livewire('/', 'pages::messages.inbox')->name('inbox');
    Route::livewire('/{conversationReference}', 'pages::messages.conversation')->name('conversation');
});

Route::middleware(['auth', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::livewire('/dashboard', 'pages::vendor.dashboard')->name('dashboard');
    Route::livewire('/products', 'pages::vendor.products')->name('products');
    Route::livewire('/products/create', 'pages::vendor.product-create')->name('products.create');
    Route::livewire('/products/{product}/edit', 'pages::vendor.product-edit')->name('products.edit');
    Route::livewire('/orders', 'pages::vendor.orders')->name('orders');
    Route::livewire('/orders/{orderReference}', 'pages::vendor.order-detail')->name('orders.show');
    Route::livewire('/sales', 'pages::vendor.sales')->name('sales');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/vendors', 'pages::admin.vendors')->name('vendors');
    Route::livewire('/vendors/{vendorProfile}', 'pages::admin.vendor-detail')->name('vendors.show');
    Route::livewire('/users', 'pages::admin.users')->name('users');
    Route::livewire('/orders', 'pages::admin.orders')->name('orders');
});

require __DIR__.'/settings.php';

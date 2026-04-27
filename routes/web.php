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
    Route::get('/vendors', [ShopController::class, 'vendors'])->name('vendors');
    Route::get('/vendors/{vendorProfile}', [ShopController::class, 'vendor'])->name('vendors.show');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');

    foreach ([
        ['/cart', 'pages.shop.cart', 'cart'],
        ['/checkout', 'pages.shop.checkout', 'checkout'],
        ['/orders', 'pages.shop.orders', 'orders'],
        ['/orders/{orderReference}', 'pages.shop.order-detail', 'orders.show'],
        ['/favorites', 'pages.shop.favorites', 'favorites'],
    ] as [$uri, $view, $name]) {
        Route::view($uri, $view)->name($name);
    }
});

Route::middleware(['auth', 'role:customer'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::view('/register', 'pages.vendor.registration')->name('registration');
});

Route::middleware(['auth', 'role:customer,vendor'])->prefix('messages')->name('messages.')->group(function () {
    foreach ([
        ['/', 'pages.messages.inbox', 'inbox'],
        ['/{conversationReference}', 'pages.messages.conversation', 'conversation'],
    ] as [$uri, $view, $name]) {
        Route::view($uri, $view)->name($name);
    }
});

Route::middleware(['auth', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/dashboard', [VendorController::class, 'index'])->name('dashboard');

    foreach ([
        ['/products', 'pages.vendor.products', 'products'],
        ['/products/create', 'pages.vendor.product-create', 'products.create'],
        ['/products/{productReference}/edit', 'pages.vendor.product-edit', 'products.edit'],
        ['/orders', 'pages.vendor.orders', 'orders'],
        ['/orders/{orderReference}', 'pages.vendor.order-detail', 'orders.show'],
        ['/sales', 'pages.vendor.sales', 'sales'],
    ] as [$uri, $view, $name]) {
        Route::view($uri, $view)->name($name);
    }
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');

    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/toggle', [AdminController::class, 'toggleUserStatus'])->name('users.toggle');
    
    foreach ([
        ['/vendors', 'pages.admin.vendors', 'vendors'],
        ['/vendors/{vendorReference}', 'pages.admin.vendor-detail', 'vendors.show'],
        ['/orders', 'pages.admin.orders', 'orders'],
    ] as [$uri, $view, $name]) {
        Route::view($uri, $view)->name($name);
    }
});

require __DIR__.'/settings.php';

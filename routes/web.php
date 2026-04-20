<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VendorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', function (Request $request) {
        return redirect()->route($request->user()->homeRoute(), $request->query());
    })->name('dashboard');
});

Route::middleware(['auth', 'role:customer'])->prefix('shop')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('shop.home');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('shop.products.show');
});

Route::middleware(['auth', 'role:vendor'])->prefix('vendor')->group(function () {
    Route::get('/dashboard', [VendorController::class, 'index'])->name('vendor.dashboard');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
});

require __DIR__.'/settings.php';

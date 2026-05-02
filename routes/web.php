<?php

use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VideoCallController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$verificationThrottle = 'throttle:'.config('fortify.limiters.verification', '6,1');

Route::get('/', [LandingPageController::class, 'index'])->name('home');

Route::middleware(['auth', $verificationThrottle])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifyLink'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verify/code', [EmailVerificationController::class, 'verify'])->name('verification.code.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Shared portal entry point that forwards users to their role-specific home route.
    Route::get('dashboard', function (Request $request) {
        return redirect()->route($request->user()->homeRoute(), $request->query());
    })->name('dashboard');

    Route::view('/notifications', 'pages.notifications.index')->name('notifications.index');
});

Route::middleware(['auth', 'verified', 'role:customer,vendor'])->prefix('customer')->name('customer.')->group(function () {
    Route::livewire('/dashboard', 'pages::customer.dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:customer,vendor'])->prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('home');
    Route::livewire('/vendors', 'pages::shop.vendors')->name('vendors');
    Route::get('/vendors/{vendorProfile}', [ShopController::class, 'vendor'])->name('vendors.show');
    Route::get('/customers/{user}', [ShopController::class, 'customer'])->name('customers.show');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');
    Route::livewire('/cart', 'pages::shop.cart')->name('cart');
    Route::livewire('/checkout', 'pages::shop.checkout')->name('checkout');
    Route::livewire('/orders', 'pages::shop.orders')->name('orders');
    Route::livewire('/orders/{orderReference}', 'pages::shop.order-detail')->name('orders.show');
    Route::livewire('/favorites', 'pages::shop.favorites')->name('favorites');
});

Route::middleware(['auth', 'verified'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::livewire('/register', 'pages::vendor.registration')->name('registration');
});

Route::middleware(['auth', 'verified', 'role:customer,vendor,admin'])->prefix('messages')->name('messages.')->group(function () {
    Route::livewire('/', 'pages::messages.inbox')->name('inbox');
    Route::livewire('/{conversationReference}', 'pages::messages.conversation')->name('conversation');
});

Route::middleware(['auth', 'verified', 'role:customer,vendor,admin'])->group(function () {
    Route::livewire('/groups/{groupId}', 'pages::messages.group-conversation')->name('messages.group');
});

Route::view('/support/video-calls', 'pages.support.video-calls')->name('support.video-calls');

Route::middleware(['auth', 'verified'])->prefix('api/calls')->name('calls.')->group(function () {
    Route::get('/ice-servers', [VideoCallController::class, 'iceServers'])->name('ice-servers');
    Route::post('/initiate', [VideoCallController::class, 'initiate'])->name('initiate');
    Route::prefix('group')->name('group.')->group(function () {
        Route::post('/initiate', [VideoCallController::class, 'initiateGroup'])->name('initiate');
        Route::post('/{call}/signal', [VideoCallController::class, 'signalGroup'])->name('signal');
        Route::post('/{call}/answer', [VideoCallController::class, 'answerGroup'])->name('answer');
        Route::post('/{call}/end', [VideoCallController::class, 'endGroup'])->name('end');
    });
    Route::post('/{call}/signal', [VideoCallController::class, 'signal'])->name('signal');
    Route::post('/{call}/answer', [VideoCallController::class, 'answer'])->name('answer');
    Route::post('/{call}/decline', [VideoCallController::class, 'decline'])->name('decline');
    Route::post('/{call}/end', [VideoCallController::class, 'end'])->name('end');
});

Route::middleware(['auth', 'verified', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::livewire('/dashboard', 'pages::vendor.dashboard')->name('dashboard');
    Route::livewire('/products', 'pages::vendor.products')->name('products');
    Route::livewire('/products/create', 'pages::vendor.product-create')->name('products.create');
    Route::livewire('/products/{product}/edit', 'pages::vendor.product-edit')->name('products.edit');
    Route::livewire('/orders', 'pages::vendor.orders')->name('orders');
    Route::livewire('/orders/{orderReference}', 'pages::vendor.order-detail')->name('orders.show');
    Route::livewire('/valued-customers', 'pages::vendor.valued-customers')->name('valued-customers');
    Route::livewire('/sales', 'pages::vendor.sales')->name('sales');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/vendors', 'pages::admin.vendors')->name('vendors');
    Route::livewire('/vendors/{vendorProfile}', 'pages::admin.vendor-detail')->name('vendors.show');
    Route::livewire('/users', 'pages::admin.users')->name('users');
    Route::livewire('/users/{user}', 'pages::admin.user-profile')->name('users.show');
    Route::livewire('/orders', 'pages::admin.orders')->name('orders');
    Route::livewire('/reports', 'pages::admin.reports')->name('reports');
    Route::livewire('/reports/{report}', 'pages::admin.report-detail')->name('reports.show');
});

require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\FallbackMediaController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/category/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/shop/{product:slug}', [ShopController::class, 'show'])->name('shop.show');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/account', [AccountController::class, 'index'])
    ->middleware('auth')
    ->name('account.index');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/checkout/paypal/create-order', [CheckoutController::class, 'createPayPalOrder'])->name('checkout.paypal.create-order');
Route::post('/checkout/paypal/capture-order', [CheckoutController::class, 'capturePayPalOrder'])->name('checkout.paypal.capture-order');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::get('/api/shop/search', [ShopController::class, 'search'])->name('api.shop.search');
Route::get('/api/locations/states', [LocationController::class, 'states'])->name('api.locations.states');
Route::get('/api/locations/cities', [LocationController::class, 'cities'])->name('api.locations.cities');

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('coupons', CouponController::class)->except('show');
    Route::resource('orders', OrderController::class)->only(['index', 'show', 'update']);
    Route::resource('products', ProductController::class)->except('show');
    Route::resource('products.variants', ProductVariantController::class)->except(['index', 'show']);
    Route::post('maintenance/clear-caches', [MaintenanceController::class, 'clearCaches'])
        ->name('maintenance.clear-caches');
    Route::post('maintenance/clear-logs', [MaintenanceController::class, 'clearLogs'])
        ->name('maintenance.clear-logs');
    Route::get('maintenance/fallback-media', [FallbackMediaController::class, 'index'])
        ->name('maintenance.fallback-media.index');
    Route::post('maintenance/fallback-media/reconvert', [FallbackMediaController::class, 'reconvertAndUse'])
        ->name('maintenance.fallback-media.reconvert');
    Route::post('maintenance/fallback-media/delete', [FallbackMediaController::class, 'destroy'])
        ->name('maintenance.fallback-media.destroy');
    Route::match(['post', 'patch'], 'products/{product}/media/{media}/primary', [ProductMediaController::class, 'makePrimary'])
        ->name('products.media.primary');
    Route::match(['post', 'delete'], 'products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
        ->name('products.media.destroy');
});

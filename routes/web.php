<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountTagFollowController;
use App\Http\Controllers\Admin\BundleDiscountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\FallbackMediaController;
use App\Http\Controllers\Admin\IntegrationSettingsController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\RegionalDiscountController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UrlRedirectController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\RegionalDiscountController as ApiRegionalDiscountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SizeProfileController;
use App\Http\Controllers\StockAlertSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/catalog', [ShopController::class, 'catalog'])->name('shop.catalog');
Route::redirect('/shop', '/');
Route::get('/shop/category/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/artist/{tag:slug}', [ShopController::class, 'artistTag'])->name('shop.artist');
Route::get('/brand/{tag:slug}', [ShopController::class, 'brandTag'])->name('shop.brand');
Route::get('/tag/{tag:slug}', [ShopController::class, 'customTag'])->name('shop.tag');
Route::get('/shop/{product:slug}', [ShopController::class, 'show'])->name('shop.show');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/account', [AccountController::class, 'index'])
    ->middleware('auth')
    ->name('account.index');

Route::patch('/account/email-preferences', [AccountController::class, 'updateEmailPreferences'])
    ->middleware('auth')
    ->name('account.email-preferences.update');

Route::patch('/account/delivery-address', [AccountController::class, 'updateDeliveryAddress'])
    ->middleware('auth')
    ->name('account.delivery-address.update');

Route::post('/account/tag-follows', [AccountTagFollowController::class, 'store'])
    ->middleware('auth')
    ->name('account.tag-follows.store');

Route::patch('/account/tag-follows/{tagFollow}', [AccountTagFollowController::class, 'update'])
    ->middleware('auth')
    ->name('account.tag-follows.update');

Route::delete('/account/tag-follows/{tagFollow}', [AccountTagFollowController::class, 'destroy'])
    ->middleware('auth')
    ->name('account.tag-follows.destroy');

Route::post('/account/size-profiles', [SizeProfileController::class, 'store'])
    ->middleware('auth')
    ->name('account.size-profiles.store');

Route::patch('/account/size-profiles/{sizeProfile}', [SizeProfileController::class, 'update'])
    ->middleware('auth')
    ->name('account.size-profiles.update');

Route::delete('/account/size-profiles/{sizeProfile}', [SizeProfileController::class, 'destroy'])
    ->middleware('auth')
    ->name('account.size-profiles.destroy');

Route::post('/stock-alert-subscriptions', [StockAlertSubscriptionController::class, 'store'])
    ->middleware('auth')
    ->name('stock-alert-subscriptions.store');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::post('/cart/coupons/select', [CartController::class, 'selectCoupons'])->name('cart.coupon.select');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/track-email', [CartController::class, 'trackEmail'])->name('cart.track-email');
Route::get('/cart/recover/{abandonment}', [CartController::class, 'recoverCart'])->name('cart.recover');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.items.destroy');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('checkout.store');
Route::post('/checkout/paypal/create-order', [CheckoutController::class, 'createPayPalOrder'])
    ->middleware('throttle:10,1')
    ->name('checkout.paypal.create-order');
Route::post('/checkout/paypal/capture-order', [CheckoutController::class, 'capturePayPalOrder'])
    ->middleware('throttle:10,1')
    ->name('checkout.paypal.capture-order');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::get('/api/shop/search', [ShopController::class, 'search'])->name('api.shop.search');
Route::get('/api/locations/states', [LocationController::class, 'states'])->name('api.locations.states');
Route::get('/api/locations/cities', [LocationController::class, 'cities'])->name('api.locations.cities');
Route::get('/api/regional-discount', [ApiRegionalDiscountController::class, 'show'])->name('api.regional-discount');

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'admin.noindex'])->group(function () {
    Route::resource('categories', CategoryController::class)->except('show');
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::resource('coupons', CouponController::class)->except('show');
    Route::resource('bundle-discounts', BundleDiscountController::class)->except('show');
    Route::resource('orders', OrderController::class)->only(['index', 'show', 'update']);
    Route::resource('products', ProductController::class)->except('show');
    Route::resource('tags', TagController::class)->except('show');
    Route::get('products/{product}/stock-alerts', [ProductController::class, 'stockAlerts'])
        ->name('products.stock-alerts');
    Route::patch('products/{product}/inline', [ProductController::class, 'inlineUpdate'])
        ->name('products.inline-update');
    Route::post('products/{product}/revert', [ProductController::class, 'revertField'])
        ->name('products.revert-field');
    Route::resource('regional-discounts', RegionalDiscountController::class)->except('show');
    Route::resource('url-redirects', UrlRedirectController::class)->except('show');
    Route::resource('products.variants', ProductVariantController::class)
        ->except(['index', 'show'])
        ->scoped();
    Route::post('maintenance/clear-caches', [MaintenanceController::class, 'clearCaches'])
        ->middleware('throttle:5,1')
        ->name('maintenance.clear-caches');
    Route::post('maintenance/clear-logs', [MaintenanceController::class, 'clearLogs'])
        ->middleware('throttle:5,1')
        ->name('maintenance.clear-logs');
    Route::get('maintenance/abandoned-cart', [MaintenanceController::class, 'editAbandonedCartSettings'])
        ->name('maintenance.abandoned-cart.edit');
    Route::post('maintenance/abandoned-cart', [MaintenanceController::class, 'updateAbandonedCartSettings'])
        ->name('maintenance.abandoned-cart.update');
    Route::get('maintenance/fallback-media', [FallbackMediaController::class, 'index'])
        ->name('maintenance.fallback-media.index');
    Route::get('maintenance/integrations', [IntegrationSettingsController::class, 'edit'])
        ->name('maintenance.integrations.edit');
    Route::post('maintenance/integrations', [IntegrationSettingsController::class, 'update'])
        ->name('maintenance.integrations.update');
    Route::post('maintenance/fallback-media/reconvert', [FallbackMediaController::class, 'reconvertAndUse'])
        ->name('maintenance.fallback-media.reconvert');
    Route::post('maintenance/fallback-media/delete', [FallbackMediaController::class, 'destroy'])
        ->name('maintenance.fallback-media.destroy');
    Route::match(['post', 'patch'], 'products/{product}/media/{media}/primary', [ProductMediaController::class, 'makePrimary'])
        ->name('products.media.primary');
    Route::match(['post', 'delete'], 'products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
        ->name('products.media.destroy');
});

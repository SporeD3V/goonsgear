<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\FallbackMediaController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/category/{category:slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/shop/{product:slug}', [ShopController::class, 'show'])->name('shop.show');

Route::get('/api/shop/search', [ShopController::class, 'search'])->name('api.shop.search');

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('categories', CategoryController::class)->except('show');
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

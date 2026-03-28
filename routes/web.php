<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
    Route::match(['post', 'patch'], 'products/{product}/media/{media}/primary', [ProductMediaController::class, 'makePrimary'])
        ->name('products.media.primary');
    Route::match(['post', 'delete'], 'products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
        ->name('products.media.destroy');
});

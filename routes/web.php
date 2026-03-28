<?php

use App\Http\Controllers\Admin\CategoryController;
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
    Route::patch('products/{product}/media/{media}/primary', [ProductMediaController::class, 'makePrimary'])
        ->name('products.media.primary');
    Route::delete('products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
        ->name('products.media.destroy');
});

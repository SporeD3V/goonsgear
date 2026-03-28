<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductMediaController extends Controller
{
    public function makePrimary(Product $product, int $media): RedirectResponse
    {
        Log::warning('Product media primary request received.', [
            'product_id' => $product->id,
            'media_id' => $media,
        ]);

        $resolvedMedia = $product->media()->find($media);

        if ($resolvedMedia === null) {
            Log::warning('Product media primary request could not resolve media for product.', [
                'product_id' => $product->id,
                'media_id' => $media,
            ]);

            return redirect()
                ->route('admin.products.edit', $product)
                ->withErrors(['media' => 'Unable to set primary media. Please refresh and try again.']);
        }

        ProductMedia::query()
            ->where('product_id', $product->id)
            ->update(['is_primary' => false]);

        $resolvedMedia->update(['is_primary' => true]);

        Log::warning('Product media primary updated.', [
            'product_id' => $product->id,
            'media_id' => $resolvedMedia->id,
        ]);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Primary media updated successfully.');
    }

    public function destroy(Product $product, int $media): RedirectResponse
    {
        Log::warning('Product media delete request received.', [
            'product_id' => $product->id,
            'media_id' => $media,
        ]);

        $resolvedMedia = $product->media()->find($media);

        if ($resolvedMedia === null) {
            Log::warning('Product media delete request could not resolve media for product.', [
                'product_id' => $product->id,
                'media_id' => $media,
            ]);

            return redirect()
                ->route('admin.products.edit', $product)
                ->withErrors(['media' => 'Unable to delete media. Please refresh and try again.']);
        }

        if ($resolvedMedia->path !== '') {
            Storage::disk($resolvedMedia->disk)->delete($resolvedMedia->path);
        }

        $wasPrimary = $resolvedMedia->is_primary;
        $resolvedMedia->delete();

        if ($wasPrimary) {
            $replacement = ProductMedia::query()
                ->where('product_id', $product->id)
                ->orderBy('position')
                ->orderBy('id')
                ->first();

            if ($replacement !== null) {
                $replacement->update(['is_primary' => true]);
            }
        }

        Log::warning('Product media deleted successfully.', [
            'product_id' => $product->id,
            'media_id' => $media,
        ]);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Media deleted successfully.');
    }
}

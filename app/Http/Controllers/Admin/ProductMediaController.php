<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class ProductMediaController extends Controller
{
    public function makePrimary(Product $product, ProductMedia $media): RedirectResponse
    {
        abort_unless($media->product_id === $product->id, 404);

        ProductMedia::query()
            ->where('product_id', $product->id)
            ->update(['is_primary' => false]);

        $media->update(['is_primary' => true]);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Primary media updated successfully.');
    }

    public function destroy(Product $product, ProductMedia $media): RedirectResponse
    {
        abort_unless($media->product_id === $product->id, 404);

        if ($media->path !== '') {
            Storage::disk($media->disk)->delete($media->path);
        }

        $wasPrimary = $media->is_primary;
        $media->delete();

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

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Media deleted successfully.');
    }
}

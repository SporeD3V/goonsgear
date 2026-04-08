<?php

use App\Concerns\ResolvesProductDisplay;
use App\Models\BundleDiscountItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    use ResolvesProductDisplay;

    public function render(): View
    {
        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->where('status', 'active')
            ->withMin([
                'variants as min_active_variant_price' => fn ($query) => $query->where('is_active', true),
            ], 'price')
            ->with([
                'primaryCategory:id,name,slug',
                'media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Product $product): Product {
                $product->setRelation('media', $product->media->map(function (ProductMedia $media): ProductMedia {
                    $media->setAttribute('catalog_path', $this->resolveGalleryPath($media));

                    return $media;
                }));

                $variantsWithStock = $product->variants->map(function (ProductVariant $variant): ProductVariant {
                    $isOutOfStock = $variant->track_inventory
                        && (int) $variant->stock_quantity <= 0
                        && ! $variant->allow_backorder
                        && ! $variant->is_preorder;

                    $variant->setAttribute('is_out_of_stock', $isOutOfStock);

                    return $variant;
                });

                $product->setAttribute('catalog_variants', $variantsWithStock);
                $product->setAttribute('catalog_selector_data', $this->buildVariantSelectorData($variantsWithStock, $product->name));

                return $product;
            });

        $bundleProductIds = BundleDiscountItem::query()
            ->whereHas('bundleDiscount', fn ($q) => $q->where('is_active', true))
            ->join('product_variants', 'bundle_discount_items.product_variant_id', '=', 'product_variants.id')
            ->pluck('product_variants.product_id')
            ->unique()
            ->all();

        return view('components.⚡new-arrivals.new-arrivals', [
            'products' => $products,
            'bundleProductIds' => $bundleProductIds,
        ]);
    }
};

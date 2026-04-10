<?php

use App\Concerns\ResolvesProductDisplay;
use App\Models\BundleDiscount;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    use ResolvesProductDisplay;

    public function render(): View
    {
        /** @var Collection<int, BundleDiscount> $bundles */
        $bundles = BundleDiscount::query()
            ->where('is_active', true)
            ->whereNotNull('bundle_price')
            ->whereNotNull('product_id')
            ->has('items')
            ->with([
                'product:id,name,slug',
                'items' => fn ($query) => $query->orderBy('position')->orderBy('id'),
                // Product bundles: product-based items
                'items.product' => fn ($query) => $query->where('status', 'active'),
                'items.product.primaryCategory:id,name,slug',
                'items.product.media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(1),
                'items.product.variants' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->filter(function (BundleDiscount $bundle): bool {
                // All items must have an active product with at least one available variant
                return $bundle->items->every(
                    fn ($item) => $item->product !== null
                        && $item->product->variants->contains(fn ($v) => $v->isAvailable())
                );
            })
            ->map(function (BundleDiscount $bundle): BundleDiscount {
                return $this->mapProductBundle($bundle);
            });

        return view('components.⚡bundle-highlights.bundle-highlights', [
            'bundles' => $bundles,
        ]);
    }

    private function mapProductBundle(BundleDiscount $bundle): BundleDiscount
    {
        // Get cheapest active variant price for each component product
        $productIds = $bundle->items->pluck('product_id')->filter()->all();

        $cheapestPrices = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->selectRaw('product_id, MIN(price) as min_price')
            ->groupBy('product_id')
            ->pluck('min_price', 'product_id');

        $totalPrice = $bundle->items->sum(function ($item) use ($cheapestPrices): float {
            $price = (float) ($cheapestPrices[$item->product_id] ?? 0);

            return $price * max(1, (int) $item->min_quantity);
        });

        $savings = $bundle->discountFor($totalPrice);

        $bundle->setAttribute('total_price', $totalPrice);
        $bundle->setAttribute('savings', $savings);

        // Set display data on each item from the product relation
        $bundle->items->each(function ($item) use ($cheapestPrices): void {
            $product = $item->product;
            $media = $product?->media->first();

            $item->setAttribute('display_product', $product);
            $item->setAttribute('display_price', (float) ($cheapestPrices[$item->product_id] ?? 0));
            $item->setAttribute('media_url', $media
                ? route('media.show', ['path' => $this->resolveGalleryPath($media)])
                : asset('images/placeholder-product.svg')
            );
        });

        return $bundle;
    }
};

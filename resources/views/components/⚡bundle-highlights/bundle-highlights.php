<?php

use App\Concerns\ResolvesProductDisplay;
use App\Models\BundleDiscount;
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
            ->has('items')
            ->with([
                'items' => fn ($query) => $query->orderBy('position')->orderBy('id'),
                'items.variant' => fn ($query) => $query->where('is_active', true),
                'items.variant.product' => fn ($query) => $query->where('status', 'active'),
                'items.variant.product.primaryCategory:id,name,slug',
                'items.variant.product.media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(1),
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->filter(function (BundleDiscount $bundle): bool {
                // Only keep bundles where all items have active variants with active products
                return $bundle->items->every(
                    fn ($item) => $item->variant !== null && $item->variant->product !== null
                );
            })
            ->map(function (BundleDiscount $bundle): BundleDiscount {
                // Compute the combined original price and savings
                $totalPrice = $bundle->items->sum(fn ($item) => (float) $item->variant->price * max(1, (int) $item->min_quantity));
                $savings = $bundle->discountFor($totalPrice);

                $bundle->setAttribute('total_price', $totalPrice);
                $bundle->setAttribute('savings', $savings);

                // Resolve media paths for each item
                $bundle->items->each(function ($item): void {
                    $media = $item->variant->product->media->first();
                    $item->setAttribute('media_url', $media
                        ? route('media.show', ['path' => $this->resolveGalleryPath($media)])
                        : asset('images/placeholder-product.svg')
                    );
                });

                return $bundle;
            });

        return view('components.⚡bundle-highlights.bundle-highlights', [
            'bundles' => $bundles,
        ]);
    }
};

<?php

use App\Concerns\ResolvesProductDisplay;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    use ResolvesProductDisplay;

    public int $currentProductId = 0;

    /** @var list<int> */
    public array $viewedIds = [];

    public function mount(int $currentProductId): void
    {
        $this->currentProductId = $currentProductId;
    }

    /**
     * Called from Alpine once localStorage IDs are available.
     *
     * @param  list<int>  $ids
     */
    public function loadProducts(array $ids): void
    {
        $this->viewedIds = array_map('intval', array_slice($ids, 0, 20));
    }

    public function render(): View
    {
        $products = collect();

        if (! empty($this->viewedIds)) {
            $ids = array_filter($this->viewedIds, fn (int $id): bool => $id !== $this->currentProductId);
            $ids = array_slice(array_values($ids), 0, 12);

            if (! empty($ids)) {
                $products = Product::query()
                    ->whereIn('id', $ids)
                    ->where('status', 'active')
                    ->withMin([
                        'variants as min_active_variant_price' => fn ($query) => $query->where('is_active', true),
                    ], 'price')
                    ->with([
                        'primaryCategory:id,name,slug',
                        'media' => fn ($query) => $query
                            ->orderByDesc('is_primary')
                            ->orderBy('position')
                            ->orderBy('id')
                            ->limit(1),
                    ])
                    ->get()
                    ->sortBy(fn (Product $product): int => array_search($product->id, $ids, true) ?: 0)
                    ->values()
                    ->map(function (Product $product): Product {
                        $product->setRelation('media', $product->media->map(function (ProductMedia $media): ProductMedia {
                            $media->setAttribute('catalog_path', $this->resolveGalleryPath($media));

                            return $media;
                        }));

                        return $product;
                    });
            }
        }

        return view('components.⚡recently-viewed.recently-viewed', [
            'products' => $products,
        ]);
    }
};

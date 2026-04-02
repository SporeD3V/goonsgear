<?php

use App\Concerns\ResolvesProductDisplay;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use ResolvesProductDisplay;
    use WithPagination;

    public ?int $forcedCategoryId = null;

    public ?int $forcedTagId = null;

    public string $search = '';

    public string $sort = 'newest';

    public ?string $minPrice = null;

    public ?string $maxPrice = null;

    public bool $includeOutOfStock = false;

    public int $sizeProfileId = 0;

    /** @var array<int, string> */
    public array $selectedSizes = [];

    public function mount(?int $forcedCategoryId = null, ?int $forcedTagId = null): void
    {
        $this->forcedCategoryId = $forcedCategoryId;
        $this->forcedTagId = $forcedTagId;

        $filters = (array) session('shop_filters', []);

        $this->search = (string) ($filters['q'] ?? '');

        $this->sort = in_array($filters['sort'] ?? '', ['newest', 'name_asc', 'name_desc', 'price_asc', 'price_desc'], true)
            ? $filters['sort']
            : 'newest';

        $this->minPrice = isset($filters['min_price']) && $filters['min_price'] !== null
            ? (string) $filters['min_price']
            : null;

        $this->maxPrice = isset($filters['max_price']) && $filters['max_price'] !== null
            ? (string) $filters['max_price']
            : null;

        $this->includeOutOfStock = (bool) ($filters['include_out_of_stock'] ?? false);
        $this->sizeProfileId = (int) ($filters['size_profile'] ?? 0);
        $this->selectedSizes = (array) ($filters['sizes'] ?? []);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
        $this->syncToSession();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'sort', 'minPrice', 'maxPrice', 'includeOutOfStock', 'sizeProfileId', 'selectedSizes']);
        session()->forget('shop_filters');
        $this->resetPage();
    }

    public function toggleSize(string $size): void
    {
        if (in_array($size, $this->selectedSizes, true)) {
            $this->selectedSizes = array_values(array_diff($this->selectedSizes, [$size]));
        } else {
            $this->selectedSizes[] = $size;
        }

        $this->resetPage();
        $this->syncToSession();
    }

    public function render(): View
    {
        $forcedCategory = $this->forcedCategoryId !== null
            ? Category::find($this->forcedCategoryId)
            : null;

        $forcedTag = $this->forcedTagId !== null
            ? Tag::find($this->forcedTagId)
            : null;

        $search = trim($this->search);
        $minPrice = $this->minPrice !== null && $this->minPrice !== '' ? max(0, (float) $this->minPrice) : null;
        $maxPrice = $this->maxPrice !== null && $this->maxPrice !== '' ? max(0, (float) $this->maxPrice) : null;
        $sort = in_array($this->sort, ['newest', 'name_asc', 'name_desc', 'price_asc', 'price_desc'], true)
            ? $this->sort
            : 'newest';

        $user = auth()->user();
        $sizeProfiles = collect();
        $activeSizeProfile = null;

        if ($user !== null) {
            $sizeProfiles = $user->sizeProfiles()
                ->orderByDesc('is_self')
                ->orderBy('name')
                ->get();

            if ($this->sizeProfileId > 0) {
                $activeSizeProfile = $sizeProfiles->firstWhere('id', $this->sizeProfileId);
            }
        }

        $profileSizes = $activeSizeProfile !== null ? $activeSizeProfile->allSizes() : [];

        $shopCategories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->whereHas('products')
                    ->orWhereHas('children', fn ($cq) => $cq->where('is_active', true)->whereHas('products'));
            })
            ->with(['children' => fn ($q) => $q->where('is_active', true)->whereHas('products')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'meta_title', 'meta_description', 'size_type']);

        $shopTags = Tag::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type']);

        $activeCategory = $forcedCategory;
        $activeTag = $forcedTag;

        // Build per-dimension size arrays for size profile filtering
        $topSizes = [];
        $bottomSizes = [];
        $shoeSizes = [];

        if ($activeSizeProfile !== null) {
            $topSizes = array_values(array_filter([$activeSizeProfile->top_size]));
            $bottomSizes = array_values(array_filter([$activeSizeProfile->bottom_size]));

            $rawShoeSize = $activeSizeProfile->shoe_size;
            if ($rawShoeSize !== null && $rawShoeSize !== '') {
                $shoeSizes = [$rawShoeSize];
                $numericShoe = (float) $rawShoeSize;
                if ($numericShoe >= 36 && $numericShoe <= 42) {
                    $shoeSizes[] = 'Smalls';
                } elseif ($numericShoe >= 43 && $numericShoe <= 46) {
                    $shoeSizes[] = 'Biggie';
                }
            }
        }

        $hasSizeProfileFilter = $activeSizeProfile !== null && $profileSizes !== [];

        $preselectSizes = $hasSizeProfileFilter
            ? array_values(array_unique(array_merge($topSizes, $bottomSizes, $shoeSizes)))
            : [];

        $allCategories = $shopCategories->merge($shopCategories->flatMap->children);
        $topCategoryIds = $allCategories->where('size_type', 'top')->pluck('id')->all();
        $bottomCategoryIds = $allCategories->where('size_type', 'bottom')->pluck('id')->all();
        $shoeCategoryIds = $allCategories->where('size_type', 'shoe')->pluck('id')->all();
        $allSizedCategoryIds = array_merge($topCategoryIds, $bottomCategoryIds, $shoeCategoryIds);

        $filterCategoryIds = [];

        if ($forcedCategory !== null) {
            $filterCategoryIds[] = $forcedCategory->id;

            if ($forcedCategory->parent_id === null) {
                $childIds = $forcedCategory->children()->where('is_active', true)->pluck('id')->all();
                $filterCategoryIds = array_merge($filterCategoryIds, $childIds);
            }
        }

        $categorySlug = $forcedCategory?->slug ?? '';
        $tagSlug = $forcedTag?->slug ?? '';
        $shouldFilterOutOfStock = $activeCategory !== null && ! $this->includeOutOfStock;

        // Build the base product query (used for both results and available sizes)
        $buildQuery = function (bool $excludeSizeFilter = false) use (
            $search, $filterCategoryIds, $categorySlug, $tagSlug,
            $shouldFilterOutOfStock, $minPrice, $maxPrice,
            $hasSizeProfileFilter, $topSizes, $bottomSizes, $shoeSizes,
            $topCategoryIds, $bottomCategoryIds, $shoeCategoryIds, $allSizedCategoryIds,
            $sort,
        ) {
            return Product::query()
                ->where('status', 'active')
                ->withMin([
                    'variants as min_active_variant_price' => fn ($query) => $query->where('is_active', true),
                ], 'price')
                ->when(
                    $search !== '',
                    fn ($query) => $query->where(function ($innerQuery) use ($search): void {
                        $innerQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('excerpt', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    })
                )
                ->when(
                    $filterCategoryIds !== [],
                    fn ($query) => $query->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $filterCategoryIds))
                )
                ->when(
                    $categorySlug === 'sale',
                    fn ($query) => $query->whereHas('variants', fn ($vq) => $vq->where('is_active', true)->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'))
                )
                ->when(
                    $tagSlug !== '',
                    fn ($query) => $query->whereHas('tags', fn ($tq) => $tq->where('slug', $tagSlug)->where('is_active', true))
                )
                ->when(
                    $shouldFilterOutOfStock,
                    fn ($query) => $query->where(function ($availabilityQuery): void {
                        $availabilityQuery
                            ->whereDoesntHave('variants', fn ($vq) => $vq->where('is_active', true))
                            ->orWhereHas('variants', function ($vq): void {
                                $vq->where('is_active', true)
                                    ->where(function ($sq): void {
                                        $sq->where('track_inventory', false)
                                            ->orWhere('allow_backorder', true)
                                            ->orWhere('is_preorder', true)
                                            ->orWhere('stock_quantity', '>', 0);
                                    });
                            });
                    })
                )
                ->when(
                    $minPrice !== null || $maxPrice !== null,
                    fn ($query) => $query->whereHas('variants', function ($vq) use ($minPrice, $maxPrice): void {
                        $vq->where('is_active', true)
                            ->when($minPrice !== null, fn ($pq) => $pq->where('price', '>=', $minPrice))
                            ->when($maxPrice !== null, fn ($pq) => $pq->where('price', '<=', $maxPrice));
                    })
                )
                ->when(
                    $hasSizeProfileFilter,
                    fn ($query) => $query->where(function ($outerQuery) use ($topSizes, $bottomSizes, $shoeSizes, $topCategoryIds, $bottomCategoryIds, $shoeCategoryIds, $allSizedCategoryIds): void {
                        if ($topSizes !== [] && $topCategoryIds !== []) {
                            $outerQuery->orWhere(function ($q) use ($topSizes, $topCategoryIds): void {
                                $q->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $topCategoryIds))
                                    ->whereHas('variants', fn ($vq) => $this->applySizeMatch($vq, $topSizes));
                            });
                        }

                        if ($bottomSizes !== [] && $bottomCategoryIds !== []) {
                            $outerQuery->orWhere(function ($q) use ($bottomSizes, $bottomCategoryIds): void {
                                $q->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $bottomCategoryIds))
                                    ->whereHas('variants', fn ($vq) => $this->applySizeMatch($vq, $bottomSizes));
                            });
                        }

                        if ($shoeSizes !== [] && $shoeCategoryIds !== []) {
                            $outerQuery->orWhere(function ($q) use ($shoeSizes, $shoeCategoryIds): void {
                                $q->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $shoeCategoryIds))
                                    ->whereHas('variants', fn ($vq) => $this->applySizeMatch($vq, $shoeSizes));
                            });
                        }

                        if ($topSizes === [] && $topCategoryIds !== []) {
                            $outerQuery->orWhereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $topCategoryIds));
                        }

                        if ($bottomSizes === [] && $bottomCategoryIds !== []) {
                            $outerQuery->orWhereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $bottomCategoryIds));
                        }

                        if ($shoeSizes === [] && $shoeCategoryIds !== []) {
                            $outerQuery->orWhereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $shoeCategoryIds));
                        }

                        if ($allSizedCategoryIds !== []) {
                            $outerQuery->orWhereDoesntHave('categories', fn ($cq) => $cq->whereIn('categories.id', $allSizedCategoryIds));
                        } else {
                            $outerQuery->orWhereRaw('1 = 1');
                        }
                    })
                )
                ->when(
                    ! $excludeSizeFilter && $this->selectedSizes !== [],
                    fn ($query) => $query->whereHas('variants', fn ($vq) => $this->applySizeMatch($vq, $this->selectedSizes, ! $this->includeOutOfStock))
                )
                ->when($sort === 'newest', fn ($query) => $query->latest('id'))
                ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
                ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
                ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('min_active_variant_price IS NULL')->orderBy('min_active_variant_price'))
                ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('min_active_variant_price')->orderByRaw('min_active_variant_price IS NULL'));
        };

        // Compute available sizes from products matching all filters EXCEPT size
        $availableSizes = $this->computeAvailableSizes($buildQuery(excludeSizeFilter: true), $shouldFilterOutOfStock);

        /** @var LengthAwarePaginator<int, Product> $products */
        $products = $buildQuery()
            ->with([
                'primaryCategory:id,name,slug',
                'tags:id,name,slug,type',
                'media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->paginate((int) config('pagination.shop_products_per_page', 12))
            ->through(function (Product $product): Product {
                $product->setRelation('media', $product->media->map(function (ProductMedia $media): ProductMedia {
                    $media->setAttribute('catalog_path', $this->resolveGalleryPath($media));

                    return $media;
                }));

                $variantsWithStock = $product->variants->map(function ($variant) {
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
            })
            ->withQueryString();

        return view('components.⚡shop-catalog.shop-catalog', [
            'products' => $products,
            'shopCategories' => $shopCategories,
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
            'shopTags' => $shopTags,
            'sizeProfiles' => $sizeProfiles,
            'activeSizeProfile' => $activeSizeProfile,
            'preselectSizes' => $preselectSizes,
            'availableSizes' => $availableSizes,
        ]);
    }

    /**
     * Compute available sizes from products matching the given base query.
     *
     * @return array<int, string>
     */
    private function computeAvailableSizes($baseQuery, bool $filterStock): array
    {
        $productIds = (clone $baseQuery)->reorder()->select('products.id')->pluck('id');

        if ($productIds->isEmpty()) {
            return [];
        }

        $variantQuery = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true);

        if ($filterStock) {
            $variantQuery->where(function ($sq): void {
                $sq->where('track_inventory', false)
                    ->orWhere('allow_backorder', true)
                    ->orWhere('is_preorder', true)
                    ->orWhere('stock_quantity', '>', 0);
            });
        }

        $variantQuery->where(function ($q): void {
            $q->where('variant_type', 'size')
                ->orWhereNotNull('option_values');
        });

        $variants = $variantQuery->get(['name', 'variant_type', 'option_values']);

        $sizes = collect();

        foreach ($variants as $variant) {
            if ($variant->variant_type === 'size') {
                $name = trim((string) $variant->name);
                if ($name !== '' && strcasecmp($name, 'Default') !== 0) {
                    $sizes->push($name);
                }
            }

            $ov = $variant->option_values;
            if (is_array($ov) && isset($ov['size']) && $ov['size'] !== '') {
                $sizes->push($ov['size']);
            }
        }

        $uniqueSizes = $sizes->unique()->filter()->values()->all();

        return $uniqueSizes !== [] ? $this->sortSizes($uniqueSizes) : [];
    }

    private function syncToSession(): void
    {
        session()->put('shop_filters', [
            'q' => $this->search,
            'sort' => $this->sort,
            'min_price' => $this->minPrice !== null && $this->minPrice !== '' ? max(0, (float) $this->minPrice) : null,
            'max_price' => $this->maxPrice !== null && $this->maxPrice !== '' ? max(0, (float) $this->maxPrice) : null,
            'include_out_of_stock' => $this->includeOutOfStock,
            'size_profile' => $this->sizeProfileId,
            'sizes' => $this->selectedSizes,
        ]);
    }
};
<?php

use App\Concerns\ResolvesProductDisplay;
use App\Models\BundleDiscountItem;
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

    public string $selectedShoeSize = '';

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
        $this->selectedShoeSize = (string) ($filters['shoe_size'] ?? '');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
        $this->syncToSession();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'sort', 'minPrice', 'maxPrice', 'includeOutOfStock', 'sizeProfileId', 'selectedSizes', 'selectedShoeSize']);
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
        $shouldFilterOutOfStock = ($activeCategory !== null || $activeTag !== null) && ! $this->includeOutOfStock;

        // Build the base product query (used for both results and available sizes)
        $buildQuery = function (bool $excludeSizeFilter = false, bool $excludePriceFilter = false) use (
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
                    $filterCategoryIds !== [] && ! in_array($categorySlug, ['sale', 'discounts', 'bundles'], true),
                    fn ($query) => $query->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $filterCategoryIds))
                )
                ->when(
                    in_array($categorySlug, ['sale', 'discounts', 'bundles'], true),
                    function ($query) use ($categorySlug) {
                        if ($categorySlug === 'bundles') {
                            return $query->whereHas('bundleDiscount', fn ($bq) => $bq->where('is_active', true)->whereNotNull('bundle_price'));
                        }

                        if ($categorySlug === 'discounts') {
                            return $query->whereHas('variants', fn ($vq) => $vq->where('is_active', true)->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'));
                        }

                        return $query->where(function ($saleQuery): void {
                            $saleQuery->whereHas('variants', fn ($vq) => $vq->where('is_active', true)->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'))
                                ->orWhereHas('bundleDiscount', fn ($bq) => $bq->where('is_active', true)->whereNotNull('bundle_price'));
                        });
                    }
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
                    ! $excludePriceFilter && ($minPrice !== null || $maxPrice !== null),
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
                    ! $excludeSizeFilter && ($this->selectedSizes !== [] || $this->selectedShoeSize !== ''),
                    function ($query) {
                        $checkStock = ! $this->includeOutOfStock;
                        $hasClothing = $this->selectedSizes !== [];
                        $hasShoe = $this->selectedShoeSize !== '';

                        $query->where(function ($outerQ) use ($hasClothing, $hasShoe, $checkStock): void {
                            if ($hasClothing) {
                                $outerQ->orWhereHas('variants', fn ($vq) => $this->applySizeMatch($vq, $this->selectedSizes, $checkStock));
                            }

                            if ($hasShoe) {
                                $shoeNum = (int) $this->selectedShoeSize;
                                $label = ($shoeNum >= 36 && $shoeNum <= 42) ? 'Smalls' : (($shoeNum >= 43 && $shoeNum <= 46) ? 'Biggie' : null);

                                if ($label !== null) {
                                    $matchSizes = [$label, $this->selectedShoeSize];
                                    $outerQ->orWhereHas('variants', fn ($vq) => $this->applySizeMatch($vq, $matchSizes, $checkStock));
                                    $outerQ->orWhereHas('variants', fn ($vq) => $this->applyUniversalMatch($vq, $checkStock));
                                }
                            }
                        });
                    }
                )
                ->when($sort === 'newest', fn ($query) => $query->latest('id'))
                ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
                ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
                ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('min_active_variant_price IS NULL')->orderBy('min_active_variant_price'))
                ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('min_active_variant_price')->orderByRaw('min_active_variant_price IS NULL'));
        };

        // Compute available sizes split by clothing vs shoe categories
        $sizeBaseQuery = $buildQuery(excludeSizeFilter: true);

        // Clothing sizes: from products NOT in shoe categories
        $clothingSizeQuery = $shoeCategoryIds !== []
            ? (clone $sizeBaseQuery)->whereDoesntHave('categories', fn ($cq) => $cq->whereIn('categories.id', $shoeCategoryIds))
            : clone $sizeBaseQuery;
        $availableSizes = $this->computeAvailableSizes($clothingSizeQuery, $shouldFilterOutOfStock);

        // Shoe sizes: from products IN shoe categories (EU numbers derived from Biggie/Smalls)
        $availableShoeSizes = [];
        if ($shoeCategoryIds !== []) {
            $shoeSizeQuery = (clone $sizeBaseQuery)->whereHas('categories', fn ($cq) => $cq->whereIn('categories.id', $shoeCategoryIds));
            $availableShoeSizes = $this->computeAvailableSizes($shoeSizeQuery, $shouldFilterOutOfStock, 'shoe');
        }

        // Compute price floor and ceiling from products matching all filters EXCEPT price
        $priceRange = $this->computePriceRange($buildQuery(excludeSizeFilter: false, excludePriceFilter: true));
        $priceFloor = $priceRange['min'];
        $priceCeiling = $priceRange['max'];

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
                    $media->setAttribute('catalog_path', $this->resolveThumbnailPath($media));

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

        $bundleProductIds = BundleDiscountItem::query()
            ->whereNotNull('product_id')
            ->whereHas('bundleDiscount', fn ($q) => $q->where('is_active', true)->whereNotNull('bundle_price'))
            ->pluck('product_id')
            ->unique()
            ->all();

        return view('components.⚡shop-catalog.shop-catalog', [
            'products' => $products,
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
            'shopTags' => $shopTags,
            'sizeProfiles' => $sizeProfiles,
            'activeSizeProfile' => $activeSizeProfile,
            'preselectSizes' => $preselectSizes,
            'availableSizes' => $availableSizes,
            'availableShoeSizes' => $availableShoeSizes,
            'priceFloor' => $priceFloor,
            'priceCeiling' => $priceCeiling,
            'bundleProductIds' => $bundleProductIds,
        ]);
    }

    /**
     * Compute available sizes from products matching the given base query.
     *
     * When $sizeType is 'shoe', numeric shoe sizes are grouped into Biggie (43–46) and Smalls (36–42).
     *
     * @return array<int, string>
     */
    private function computeAvailableSizes($baseQuery, bool $filterStock, ?string $sizeType = null): array
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

        $variants = $variantQuery->get(['name', 'variant_type', 'option_values']);

        $sizes = collect();
        $hasUnsizedVariants = false;

        foreach ($variants as $variant) {
            // 1. Prefer explicit option_values JSON size
            $ov = $variant->option_values;
            if (is_array($ov) && isset($ov['size']) && $ov['size'] !== '') {
                $sizes->push($ov['size']);

                continue;
            }

            // 2. Extract size from end of variant name after known delimiters
            $name = trim((string) $variant->name);
            if ($name === '' || strcasecmp($name, 'Default') === 0) {
                $hasUnsizedVariants = true;

                continue;
            }

            $extracted = $this->extractSizeFromName($name);
            if ($extracted !== null) {
                $sizes->push($extracted);
            } else {
                $hasUnsizedVariants = true;
            }
        }

        // When viewing a shoe category, determine which EU shoe sizes are available
        // based on Biggie (43–46) and Smalls (36–42) variant labels
        if ($sizeType === 'shoe') {
            $shoeLabels = $sizes->map(function (string $size): ?string {
                $lower = strtolower($size);
                if ($lower === 'smalls' || $lower === 'biggie') {
                    return $lower === 'smalls' ? 'Smalls' : 'Biggie';
                }
                if (preg_match('/^\d{2}(\.\d)?$/', $size)) {
                    $numeric = (float) $size;
                    if ($numeric >= 36 && $numeric <= 42) {
                        return 'Smalls';
                    }
                    if ($numeric >= 43 && $numeric <= 46) {
                        return 'Biggie';
                    }
                }

                return null;
            })->filter()->unique()->values();

            $euSizes = [];
            if ($shoeLabels->contains('Smalls')) {
                $euSizes = array_merge($euSizes, range(36, 42));
            }
            if ($shoeLabels->contains('Biggie')) {
                $euSizes = array_merge($euSizes, range(43, 46));
            }

            return array_map('strval', $euSizes);
        }

        $uniqueSizes = $sizes->unique()->filter()->values()->all();

        return $uniqueSizes !== [] ? $this->sortSizes($uniqueSizes) : [];
    }

    /**
     * Extract a size value from the trailing segment of a variant name.
     *
     * Looks for the last occurrence of known delimiters (", ", "- ", "/ ", "| ")
     * and returns the trailing segment only if it looks like a recognized size.
     */
    private function extractSizeFromName(string $name): ?string
    {
        $knownSizes = [
            'xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', 'xxxxl', 'xxxxxl',
            '2xl', '3xl', '4xl', '5xl',
            'smalls', 'biggie',
        ];

        // Also recognize numeric shoe sizes (36–50)
        $delimiters = [', ', ' - ', ' / ', ' | '];

        foreach (array_reverse($delimiters) as $delimiter) {
            $lastPos = strrpos($name, $delimiter);
            if ($lastPos !== false) {
                $candidate = trim(substr($name, $lastPos + strlen($delimiter)));
                if ($candidate !== '' && $this->looksLikeSize($candidate, $knownSizes)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Check if a string looks like a garment/shoe size.
     *
     * @param  array<int, string>  $knownSizes
     */
    private function looksLikeSize(string $candidate, array $knownSizes): bool
    {
        // Exact match against known size labels
        if (in_array(strtolower($candidate), $knownSizes, true)) {
            return true;
        }

        // Numeric shoe sizes (e.g. "42", "44.5")
        if (preg_match('/^\d{2}(\.\d)?$/', $candidate)) {
            return true;
        }

        return false;
    }

    /**
     * Compute the min and max active variant prices for the given query.
     *
     * @return array{min: float, max: float}
     */
    private function computePriceRange($baseQuery): array
    {
        $productIds = (clone $baseQuery)->reorder()->select('products.id')->pluck('id');

        if ($productIds->isEmpty()) {
            return ['min' => 0, 'max' => 0];
        }

        $stats = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return [
            'min' => floor((float) ($stats->min_price ?? 0)),
            'max' => ceil((float) ($stats->max_price ?? 0)),
        ];
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
            'shoe_size' => $this->selectedShoeSize,
        ]);
    }

    /**
     * Expand Biggie/Smalls labels to include the numeric shoe sizes they represent.
     *
     * @param  array<int, string>  $sizes
     * @return array<int, string>
     */
    private function expandSizeLabels(array $sizes): array
    {
        $expanded = $sizes;

        if (in_array('Smalls', $sizes, true)) {
            $expanded = array_merge($expanded, array_map('strval', range(36, 42)));
        }

        if (in_array('Biggie', $sizes, true)) {
            $expanded = array_merge($expanded, array_map('strval', range(43, 46)));
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Match variants that have no recognizable size (universal/one-size-fits-all).
     *
     * Excludes variants whose name ends with a known size after a delimiter,
     * have a size in option_values, or are named after a recognized size.
     */
    private function applyUniversalMatch($variantQuery, bool $checkStock = true): void
    {
        $variantQuery->where('is_active', true);

        if ($checkStock) {
            $variantQuery->where(function ($stockQuery): void {
                $stockQuery->where('track_inventory', false)
                    ->orWhere('allow_backorder', true)
                    ->orWhere('is_preorder', true)
                    ->orWhere('stock_quantity', '>', 0);
            });
        }

        // Exclude variants that have a recognizable size
        $variantQuery->where(function ($q): void {
            $q->whereNull('option_values->size')
                ->orWhere('option_values->size', '');
        });

        $knownSizes = [
            'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL', 'XXXXXL',
            '2XL', '3XL', '4XL', '5XL',
            'Smalls', 'Biggie',
        ];

        foreach ($knownSizes as $size) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $size);
            $variantQuery->where('name', 'NOT LIKE', '%- '.$escaped)
                ->where('name', 'NOT LIKE', '%, '.$escaped)
                ->where('name', 'NOT LIKE', '%/ '.$escaped)
                ->where('name', 'NOT LIKE', '%| '.$escaped);
        }

        // Exclude numeric shoe sizes (36–50) at end of name
        $variantQuery->whereRaw("name NOT REGEXP '(- |, |/ |\\\\| )[0-9]{2}(\\\\.[0-9])?$'");
    }
};

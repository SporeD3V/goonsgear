<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        return $this->renderIndex($request);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->renderIndex($request, $category);
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === 'active', 404);

        $product->load([
            'primaryCategory:id,name',
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id'),
            'media' => fn ($query) => $query
                ->with('variant:id,name')
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        $product->setRelation('media', $product->media->map(function (ProductMedia $media): ProductMedia {
            $media->setAttribute('display_path', $this->resolveGalleryPath($media));
            $media->setAttribute('thumbnail_path', $this->resolveThumbnailPath($media));
            $media->setAttribute('zoom_path', $this->resolveZoomPath($media));

            return $media;
        }));

        $primaryMedia = $product->media->first();
        $currentUser = request()->user();

        $variantsWithStockState = $product->variants->map(function ($variant) {
            $isOutOfStock = $variant->track_inventory
                && (int) $variant->stock_quantity <= 0
                && ! $variant->allow_backorder
                && ! $variant->is_preorder;

            $variant->setAttribute('is_out_of_stock', $isOutOfStock);

            return $variant;
        });

        $variantSelectorData = $this->buildVariantSelectorData($variantsWithStockState, $product->name);

        $activeStockAlertVariantIds = [];

        if ($currentUser !== null && $variantsWithStockState->isNotEmpty()) {
            $activeStockAlertVariantIds = StockAlertSubscription::query()
                ->where('user_id', $currentUser->id)
                ->where('is_active', true)
                ->whereIn('product_variant_id', $variantsWithStockState->pluck('id'))
                ->pluck('product_variant_id')
                ->all();
        }

        $seo = [
            'title' => $product->meta_title ?: $product->name.' | GoonsGear',
            'description' => $product->meta_description ?: ($product->plainExcerpt() ?: 'Shop '.$product->name.' at GoonsGear.'),
            'canonical_url' => route('shop.show', $product),
            'og_image' => $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null,
        ];

        return view('shop.show', [
            'product' => $product,
            'variantsWithStockState' => $variantsWithStockState,
            'variantSelectorData' => $variantSelectorData,
            'activeStockAlertVariantIds' => $activeStockAlertVariantIds,
            'seo' => $seo,
        ]);
    }

    /**
     * @return array{
     *   groups: array<string, array{label: string, values: array<int, string>}>,
     *   variantAttributesById: array<int, array<string, string>>,
     *   attributeOrder: array<int, string>
     * }
     */
    private function buildVariantSelectorData(Collection $variants, string $productName = ''): array
    {
        $rawVariantAttributes = [];
        $groupValues = [];

        foreach ($variants as $variant) {
            $attributes = $this->extractVariantAttributes($variant, $productName);
            $rawVariantAttributes[$variant->id] = $attributes;

            foreach ($attributes as $key => $value) {
                $canonicalKey = $this->canonicalAttributeKey($key);

                if (! isset($groupValues[$canonicalKey])) {
                    $groupValues[$canonicalKey] = [];
                }

                if ($value !== '' && ! in_array($value, $groupValues[$canonicalKey], true)) {
                    $groupValues[$canonicalKey][] = $value;
                }
            }
        }

        $attributeKeys = collect($groupValues)
            ->filter(fn (array $values) => count($values) > 1)
            ->keys()
            ->sortBy(fn (string $key) => match ($key) {
                'size' => '00-size',
                'color' => '01-color',
                default => '10-'.$key,
            })
            ->values()
            ->all();

        $groups = [];
        foreach ($attributeKeys as $key) {
            $values = $groupValues[$key] ?? [];

            if ($key === 'size') {
                $values = $this->sortSizes($values);
            } else {
                natcasesort($values);
                $values = array_values($values);
            }

            $groups[$key] = [
                'label' => $this->attributeLabelFromKey($key),
                'values' => $values,
            ];
        }

        $variantAttributesById = [];
        foreach ($rawVariantAttributes as $variantId => $attributes) {
            $normalizedAttributes = [];

            foreach ($attributes as $attributeKey => $attributeValue) {
                $canonicalKey = $this->canonicalAttributeKey($attributeKey);

                if (! in_array($canonicalKey, $attributeKeys, true)) {
                    continue;
                }

                $value = trim($attributeValue);
                if ($value === '' || isset($normalizedAttributes[$canonicalKey])) {
                    continue;
                }

                $normalizedAttributes[$canonicalKey] = $value;
            }

            $variantAttributesById[$variantId] = $normalizedAttributes;
        }

        return [
            'groups' => $groups,
            'variantAttributesById' => $variantAttributesById,
            'attributeOrder' => $attributeKeys,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractVariantAttributes($variant, string $productName = ''): array
    {
        $attributes = [];

        if (is_array($variant->option_values) && $variant->option_values !== []) {
            foreach ($variant->option_values as $rawKey => $rawValue) {
                if (! is_scalar($rawValue)) {
                    continue;
                }

                $value = trim((string) $rawValue);
                if ($value === '') {
                    continue;
                }

                $key = $this->normalizeAttributeKey((string) $rawKey, $value);
                $attributes[$key] = $value;
            }

            if ($attributes !== []) {
                return $attributes;
            }
        }

        $rawName = trim((string) $variant->name);
        if ($rawName === '' || strcasecmp($rawName, 'Default') === 0) {
            return [];
        }

        $explicitVariantType = strtolower((string) ($variant->variant_type ?? ''));
        if (in_array($explicitVariantType, ['size', 'color'], true)) {
            $typedValue = $rawName;

            if ($productName !== '') {
                $escapedProductName = preg_quote(trim($productName), '/');
                $typedValue = preg_replace('/^'.$escapedProductName.'\s*[\-|\|,\/]\s*/i', '', $typedValue) ?? $typedValue;
            }

            $typedValue = trim($typedValue);
            if ($typedValue !== '') {
                if (ProductVariant::detectTypeFromName($typedValue) === $explicitVariantType) {
                    return [$explicitVariantType => $typedValue];
                }
            }
        }

        // Strip product name prefix before splitting. WooCommerce variant names always start with
        // the parent product name followed by a separator, e.g. "Onyx - All White Shirt - M, Black"
        // → "M, Black" after stripping "Onyx - All White Shirt - ". Without this, product name
        // fragments like "All White" get misclassified as color values, shadowing the real color.
        $nameForSplit = $rawName;
        if ($productName !== '') {
            $escapedProductName = preg_quote(trim($productName), '/');
            $stripped = preg_replace('/^'.$escapedProductName.'\s*[\-|\|,\/]\s*/i', '', $rawName);
            if ($stripped !== null && $stripped !== $rawName) {
                $nameForSplit = $stripped;
            }
        }

        $parts = preg_split('/\s*[\|,\/-]\s*/', $nameForSplit) ?: [];
        $parts = array_values(array_filter(array_map(fn (string $part) => trim($part), $parts), fn (string $part) => $part !== ''));

        $parts = $this->stripProductNameLeadingParts($parts, $productName);

        if ($parts === []) {
            return [];
        }

        foreach ($parts as $index => $value) {
            $baseKey = $this->classifyAttributeKey($value, (string) ($variant->variant_type ?? ''), $index);
            $key = $baseKey;
            $suffix = 2;

            while (array_key_exists($key, $attributes)) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    private function stripProductNameLeadingParts(array $parts, string $productName): array
    {
        if ($productName === '' || count($parts) <= 1) {
            return $parts;
        }

        $normalizedProductName = $this->normalizeComparisonValue($productName);
        if ($normalizedProductName === '') {
            return $parts;
        }

        $matchedPartCount = 0;
        $combinedPrefix = '';

        foreach ($parts as $index => $part) {
            $normalizedPart = $this->normalizeComparisonValue($part);
            if ($normalizedPart === '') {
                break;
            }

            $combinedPrefix = trim($combinedPrefix.' '.$normalizedPart);

            if ($combinedPrefix === $normalizedProductName) {
                $matchedPartCount = $index + 1;
                break;
            }

            if (! str_starts_with($normalizedProductName, $combinedPrefix.' ')) {
                break;
            }
        }

        if ($matchedPartCount > 0 && $matchedPartCount < count($parts)) {
            return array_values(array_slice($parts, $matchedPartCount));
        }

        return $parts;
    }

    private function normalizeComparisonValue(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeAttributeKey(string $rawKey, string $value): string
    {
        $normalized = Str::of($rawKey)
            ->replace(['attribute_', 'pa_'], '')
            ->snake()
            ->toString();

        $normalized = $this->canonicalAttributeKey($normalized);

        if (in_array($normalized, ['colour', 'farbe', 'couleur'], true)) {
            return 'color';
        }

        if (in_array($normalized, ['groesse', 'taille'], true)) {
            return 'size';
        }

        if ($normalized !== '') {
            return $normalized;
        }

        return $this->classifyAttributeKey($value, '', 0);
    }

    private function canonicalAttributeKey(string $key): string
    {
        return match (true) {
            preg_match('/^(color|size)_\d+$/', $key) === 1 => preg_replace('/_\d+$/', '', $key) ?? $key,
            default => $key,
        };
    }

    private function classifyAttributeKey(string $value, string $variantType, int $index): string
    {
        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return 'option_'.($index + 1);
        }

        return match (ProductVariant::detectTypeFromName($trimmedValue)) {
            'size' => 'size',
            'color' => 'color',
            default => 'option_'.($index + 1),
        };
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function sortSizes(array $values): array
    {
        $sizeOrder = [
            'xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', 'xxxxl', 'xxxxxl',
            '2xl', '3xl', '4xl', '5xl',
        ];

        usort($values, function (string $left, string $right) use ($sizeOrder): int {
            $leftIndex = array_search(strtolower($left), $sizeOrder, true);
            $rightIndex = array_search(strtolower($right), $sizeOrder, true);

            if ($leftIndex !== false && $rightIndex !== false) {
                return $leftIndex <=> $rightIndex;
            }

            if ($leftIndex !== false) {
                return -1;
            }

            if ($rightIndex !== false) {
                return 1;
            }

            return strnatcasecmp($left, $right);
        });

        return $values;
    }

    private function attributeLabelFromKey(string $key): string
    {
        $canonicalKey = $this->canonicalAttributeKey($key);

        return match ($canonicalKey) {
            'size' => 'Size',
            'color' => 'Color',
            default => 'Product options',
        };
    }

    private function resolveZoomPath(ProductMedia $media): string
    {
        if (str_starts_with((string) $media->mime_type, 'video/')) {
            return $media->path;
        }

        $path = (string) $media->path;
        $disk = (string) ($media->disk ?: 'public');
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'] ?? '';

        if ($filename === '') {
            return $path;
        }

        $baseName = $filename;
        foreach (['-thumbnail-200x200', '-hero-1200x600', '-gallery-600x600'] as $suffix) {
            if (str_ends_with($baseName, $suffix)) {
                $baseName = substr($baseName, 0, -strlen($suffix));
                break;
            }
        }

        $basePath = ($directory !== '' && $directory !== '.' ? $directory.'/' : '').$baseName;
        $candidates = [
            $basePath.'.avif',
            $basePath.'.webp',
            $path,
        ];

        if (str_contains($path, '/fallback/')) {
            $galleryBasePath = str_replace('/fallback/', '/gallery/', $basePath);
            array_unshift($candidates, $galleryBasePath.'.avif', $galleryBasePath.'.webp');
        }

        foreach ($candidates as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $path;
    }

    private function resolveGalleryPath(ProductMedia $media): string
    {
        return $this->resolveSizedPath($media, $media->getGalleryPath());
    }

    private function resolveThumbnailPath(ProductMedia $media): string
    {
        return $this->resolveSizedPath($media, $media->getThumbnailPath());
    }

    private function resolveSizedPath(ProductMedia $media, string $preferredPath): string
    {
        if (str_starts_with((string) $media->mime_type, 'video/')) {
            return $media->path;
        }

        $disk = (string) ($media->disk ?: 'public');
        $candidates = [$preferredPath];

        if (str_contains($preferredPath, '/fallback/')) {
            $galleryPreferredPath = str_replace('/fallback/', '/gallery/', $preferredPath);
            array_unshift($candidates, $galleryPreferredPath);
        }

        $candidates[] = $media->path;

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $media->path;
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->trim()->toString();

        if (strlen($search) < 2) {
            return response()->json(['results' => []]);
        }

        $results = Product::query()
            ->where('status', 'active')
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('excerpt', 'like', '%'.$search.'%');
            })
            ->with([
                'primaryCategory:id,name',
                'media' => fn ($query) => $query
                    ->select(['id', 'product_id', 'disk', 'path', 'mime_type', 'alt_text', 'is_primary', 'position'])
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select(['id', 'product_id', 'price'])
                    ->orderBy('price'),
            ])
            ->select(['id', 'primary_category_id', 'name', 'slug', 'excerpt'])
            ->limit(8)
            ->get()
            ->map(function ($product) {
                $primaryMedia = $product->media->first();
                $secondaryMedia = $product->media->skip(1)->first();
                $primaryImagePath = $primaryMedia ? $this->resolveThumbnailPath($primaryMedia) : null;
                $secondaryImagePath = $secondaryMedia ? $this->resolveThumbnailPath($secondaryMedia) : null;
                $minPrice = $product->variants->min('price');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'excerpt' => strip_tags((string) $product->excerpt),
                    'category' => $product->primaryCategory?->name,
                    'price' => $minPrice !== null ? (float) $minPrice : null,
                    'image' => $primaryImagePath ? route('media.show', ['path' => $primaryImagePath]) : null,
                    'secondary_image' => $secondaryImagePath ? route('media.show', ['path' => $secondaryImagePath]) : null,
                    'url' => route('shop.show', $product),
                ];
            });

        return response()->json(['results' => $results]);
    }

    private function renderIndex(Request $request, ?Category $forcedCategory = null): View
    {
        $search = $request->string('q')->trim()->toString();
        $requestedCategorySlug = $request->string('category')->trim()->toString();
        $categorySlug = $forcedCategory?->slug ?? $requestedCategorySlug;
        $tagSlug = $request->string('tag')->trim()->toString();
        $minPrice = is_numeric($request->input('min_price')) ? max(0, (float) $request->input('min_price')) : null;
        $maxPrice = is_numeric($request->input('max_price')) ? max(0, (float) $request->input('max_price')) : null;
        $showOutOfStock = $request->boolean('include_out_of_stock');
        $sort = $request->string('sort')->trim()->toString();
        $sort = in_array($sort, ['newest', 'name_asc', 'name_desc', 'price_asc', 'price_desc'], true) ? $sort : 'newest';

        $sizeProfileId = $request->integer('size_profile');
        $activeSizeProfile = null;
        $sizeProfiles = collect();
        $user = $request->user();

        if ($user !== null) {
            $sizeProfiles = $user->sizeProfiles()
                ->orderByDesc('is_self')
                ->orderBy('name')
                ->get();

            if ($sizeProfileId > 0) {
                $activeSizeProfile = $sizeProfiles->firstWhere('id', $sizeProfileId);
            }
        }

        $profileSizes = $activeSizeProfile !== null ? $activeSizeProfile->allSizes() : [];

        $shopCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'meta_title', 'meta_description']);

        $shopTags = Tag::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type']);

        $activeCategory = $forcedCategory;

        if ($activeCategory === null && $categorySlug !== '') {
            $activeCategory = $shopCategories->firstWhere('slug', $categorySlug);
        }

        $shouldFilterOutOfStock = $activeCategory !== null && ! $showOutOfStock;

        /** @var LengthAwarePaginator<int, Product> $products */
        $products = Product::query()
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
                $categorySlug !== '',
                fn ($query) => $query->whereHas('primaryCategory', fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug))
            )
            ->when(
                $tagSlug !== '',
                fn ($query) => $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('slug', $tagSlug)->where('is_active', true))
            )
            ->when(
                $shouldFilterOutOfStock,
                fn ($query) => $query->where(function ($availabilityQuery): void {
                    $availabilityQuery
                        ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery->where('is_active', true))
                        ->orWhereHas('variants', function ($variantQuery): void {
                            $variantQuery->where('is_active', true)
                                ->where(function ($stockQuery): void {
                                    $stockQuery->where('track_inventory', false)
                                        ->orWhere('allow_backorder', true)
                                        ->orWhere('is_preorder', true)
                                        ->orWhere('stock_quantity', '>', 0);
                                });
                        });
                })
            )
            ->when(
                $minPrice !== null || $maxPrice !== null,
                fn ($query) => $query->whereHas('variants', function ($variantQuery) use ($minPrice, $maxPrice): void {
                    $variantQuery->where('is_active', true)
                        ->when($minPrice !== null, fn ($priceQuery) => $priceQuery->where('price', '>=', $minPrice))
                        ->when($maxPrice !== null, fn ($priceQuery) => $priceQuery->where('price', '<=', $maxPrice));
                })
            )
            ->when(
                $profileSizes !== [],
                fn ($query) => $query->whereHas('variants', function ($variantQuery) use ($profileSizes): void {
                    $variantQuery->where('is_active', true)
                        ->where(function ($sizeQuery) use ($profileSizes): void {
                            $sizeQuery->where(function ($q) use ($profileSizes): void {
                                $q->where('variant_type', 'size')
                                    ->whereIn('name', $profileSizes);
                            });

                            foreach ($profileSizes as $size) {
                                $sizeQuery->orWhere('option_values->size', $size);
                            }
                        });
                })
            )
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
            ->when($sort === 'newest', fn ($query) => $query->latest('id'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('min_active_variant_price IS NULL')->orderBy('min_active_variant_price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('min_active_variant_price')->orderByRaw('min_active_variant_price IS NULL'))
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

        $pageTitle = $activeCategory?->meta_title ?: ($activeCategory ? $activeCategory->name.' | Shop | GoonsGear' : 'Shop | GoonsGear');
        $pageDescription = $activeCategory?->meta_description ?: (strip_tags((string) $activeCategory?->description) ?: 'Browse active GoonsGear products by category, newest arrivals, and price.');

        return view('shop.index', [
            'products' => $products,
            'shopCategories' => $shopCategories,
            'activeCategory' => $activeCategory,
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'tag' => $tagSlug,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'include_out_of_stock' => $showOutOfStock,
                'sort' => $sort,
                'size_profile' => $sizeProfileId,
            ],
            'shopTags' => $shopTags,
            'sizeProfiles' => $sizeProfiles,
            'activeSizeProfile' => $activeSizeProfile,
            'seo' => [
                'title' => $pageTitle,
                'description' => $pageDescription,
            ],
        ]);
    }
}

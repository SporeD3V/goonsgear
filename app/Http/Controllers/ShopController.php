<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\StockAlertSubscription;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'activeStockAlertVariantIds' => $activeStockAlertVariantIds,
            'seo' => $seo,
        ]);
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
                    ->select(['id', 'product_id', 'path', 'alt_text', 'is_primary', 'position'])
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
                $minPrice = $product->variants->min('price');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'excerpt' => strip_tags((string) $product->excerpt),
                    'category' => $product->primaryCategory?->name,
                    'price' => $minPrice !== null ? (float) $minPrice : null,
                    'image' => $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null,
                    'secondary_image' => $secondaryMedia ? route('media.show', ['path' => $secondaryMedia->path]) : null,
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
            ->with([
                'primaryCategory:id,name,slug',
                'tags:id,name,slug,type',
                'media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select(['id', 'product_id', 'price']),
            ])
            ->when($sort === 'newest', fn ($query) => $query->latest('id'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('min_active_variant_price IS NULL')->orderBy('min_active_variant_price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('min_active_variant_price')->orderByRaw('min_active_variant_price IS NULL'))
            ->paginate((int) config('pagination.shop_products_per_page', 12))
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
            ],
            'shopTags' => $shopTags,
            'seo' => [
                'title' => $pageTitle,
                'description' => $pageDescription,
            ],
        ]);
    }
}

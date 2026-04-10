<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesProductDisplay;
use App\Models\BundleDiscount;
use App\Models\BundleDiscountItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    use ResolvesProductDisplay;

    public function index(Request $request): View
    {
        return $this->renderIndex($request);
    }

    public function catalog(Request $request): View
    {
        return $this->renderIndex($request, showCatalog: true);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->renderIndex($request, $category);
    }

    public function artistTag(Request $request, Tag $tag): View
    {
        abort_unless($tag->is_active && $tag->type === 'artist', 404);

        return $this->renderIndex($request, forcedTag: $tag);
    }

    public function brandTag(Request $request, Tag $tag): View
    {
        abort_unless($tag->is_active && $tag->type === 'brand', 404);

        return $this->renderIndex($request, forcedTag: $tag);
    }

    public function customTag(Request $request, Tag $tag): View
    {
        abort_unless($tag->is_active && $tag->type === 'custom', 404);

        return $this->renderIndex($request, forcedTag: $tag);
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === 'active', 404);

        $product->load([
            'primaryCategory:id,name,slug,parent_id',
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

        $product->setRelation('media', $product->media->map(function ($media): ProductMedia {
            /** @var ProductMedia $media */
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

        // Build breadcrumbs: Home > Category > Product
        $breadcrumbs = [['name' => 'Home', 'url' => route('shop.index')]];

        /** @var Category|null $primaryCategory */
        $primaryCategory = $product->primaryCategory;

        if ($primaryCategory !== null) {
            if ($primaryCategory->parent_id !== null) {
                /** @var Category|null $parentCategory */
                $parentCategory = $primaryCategory->parent ?? Category::find($primaryCategory->parent_id);

                if ($parentCategory !== null) {
                    $breadcrumbs[] = ['name' => $parentCategory->name, 'url' => route('shop.category', $parentCategory)];
                }
            }

            $breadcrumbs[] = ['name' => $primaryCategory->name, 'url' => route('shop.category', $primaryCategory)];
        }

        $breadcrumbs[] = ['name' => $product->name, 'url' => null];

        // Load bundle data if this product is linked to a bundle
        $bundleData = $this->loadBundleData($product);

        // Check if this product is a component of an active bundle
        $parentBundle = $this->loadParentBundleData($product);

        return view('shop.show', [
            'product' => $product,
            'variantsWithStockState' => $variantsWithStockState,
            'variantSelectorData' => $variantSelectorData,
            'activeStockAlertVariantIds' => $activeStockAlertVariantIds,
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
            'bundleData' => $bundleData,
            'parentBundle' => $parentBundle,
        ]);
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
            ->map(function (Product $product) {
                /** @var ProductMedia|null $primaryMedia */
                $primaryMedia = $product->media->first();
                /** @var ProductMedia|null $secondaryMedia */
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

    /**
     * Load bundle component data if this product is linked to a bundle discount.
     *
     * @return array{bundle: BundleDiscount, components: array<int, array{product_id: int, name: string, slug: string, media_url: string|null, variants: array<int, array{id: int, name: string, price: float, in_stock: bool}>}>, bundle_price: float, component_total: float, savings: float}|null
     */
    private function loadBundleData(Product $product): ?array
    {
        $bundle = BundleDiscount::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereNotNull('bundle_price')
            ->with([
                'items' => fn ($query) => $query->orderBy('position')->orderBy('id'),
                'items.product' => fn ($query) => $query->where('status', 'active'),
                'items.product.variants' => fn ($query) => $query->where('is_active', true)->orderBy('position')->orderBy('id'),
                'items.product.primaryCategory:id,name,slug',
                'items.product.media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(1),
            ])
            ->first();

        if ($bundle === null) {
            return null;
        }

        $components = [];
        $componentTotal = 0.0;

        /** @var BundleDiscountItem $item */
        foreach ($bundle->items as $item) {
            /** @var Product|null $componentProduct */
            $componentProduct = $item->product;

            if ($componentProduct === null) {
                continue;
            }

            /** @var ProductMedia|null $media */
            $media = $componentProduct->media->first();
            $mediaUrl = $media
                ? route('media.show', ['path' => $this->resolveGalleryPath($media)])
                : asset('images/placeholder-product.svg');

            /** @var Collection<int, ProductVariant> $variantModels */
            $variantModels = $componentProduct->variants;
            $variants = $variantModels->map(fn (ProductVariant $variant): array => [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'price' => (float) $variant->price,
                'in_stock' => $variant->isAvailable(),
            ])->all();

            $cheapestPrice = $componentProduct->variants->min('price');
            $componentTotal += (float) $cheapestPrice * max(1, (int) $item->min_quantity);

            $components[] = [
                'product_id' => (int) $componentProduct->id,
                'name' => $componentProduct->name,
                'slug' => $componentProduct->slug,
                'category' => $componentProduct->primaryCategory->name ?? 'Uncategorized',
                'media_url' => $mediaUrl,
                'variants' => $variants,
                'min_quantity' => max(1, (int) $item->min_quantity),
            ];
        }

        if ($components === []) {
            return null;
        }

        $bundlePrice = (float) $bundle->bundle_price;
        $savings = $bundle->calculateSavings($componentTotal);

        return [
            'bundle' => $bundle,
            'components' => $components,
            'bundle_price' => $bundlePrice,
            'component_total' => round($componentTotal, 2),
            'savings' => $savings,
        ];
    }

    /**
     * Check if this product is a component of an active bundle and return bundle product info.
     *
     * @return array{name: string, slug: string, bundle_price: float, savings: float, media_url: string|null}|null
     */
    private function loadParentBundleData(Product $product): ?array
    {
        /** @var BundleDiscountItem|null $bundleItem */
        $bundleItem = BundleDiscountItem::query()
            ->where('product_id', $product->id)
            ->whereHas('bundleDiscount', fn ($q) => $q->where('is_active', true)->whereNotNull('bundle_price')->whereNotNull('product_id'))
            ->with([
                'bundleDiscount.product' => fn ($q) => $q->where('status', 'active'),
                'bundleDiscount.product.media' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('position')->limit(1),
                'bundleDiscount.items.product.variants' => fn ($q) => $q->where('is_active', true)->orderBy('price'),
            ])
            ->first();

        if ($bundleItem === null) {
            return null;
        }

        /** @var BundleDiscount $bundle */
        $bundle = $bundleItem->bundleDiscount;

        /** @var Product|null $bundleProduct */
        $bundleProduct = $bundle->product;

        if ($bundleProduct === null) {
            return null;
        }

        /** @var ProductMedia|null $media */
        $media = $bundleProduct->media->first();
        $mediaUrl = $media
            ? route('media.show', ['path' => $this->resolveThumbnailPath($media)])
            : null;

        $componentTotal = 0.0;

        /** @var BundleDiscountItem $item */
        foreach ($bundle->items as $item) {
            /** @var Product|null $componentProduct */
            $componentProduct = $item->product;

            if ($componentProduct !== null) {
                $cheapest = $componentProduct->variants->min('price');
                $componentTotal += (float) $cheapest * max(1, (int) $item->min_quantity);
            }
        }

        return [
            'name' => $bundleProduct->name,
            'slug' => $bundleProduct->slug,
            'bundle_price' => (float) $bundle->bundle_price,
            'savings' => $bundle->calculateSavings($componentTotal),
            'media_url' => $mediaUrl,
        ];
    }

    private function renderIndex(Request $request, ?Category $forcedCategory = null, ?Tag $forcedTag = null, bool $showCatalog = false): View
    {
        $activeCategory = $forcedCategory;
        $activeTag = $forcedTag;

        // Build breadcrumbs
        $breadcrumbs = [['name' => 'Home', 'url' => route('shop.index')]];

        if ($activeCategory !== null) {
            if ($activeCategory->parent_id !== null) {
                /** @var Category|null $parentCategory */
                $parentCategory = $activeCategory->parent ?? Category::find($activeCategory->parent_id);

                if ($parentCategory !== null) {
                    $breadcrumbs[] = ['name' => $parentCategory->name, 'url' => route('shop.category', $parentCategory)];
                }
            }

            $breadcrumbs[] = ['name' => $activeCategory->name, 'url' => null];
        } elseif ($activeTag !== null) {
            $typeLabel = match ($activeTag->type) {
                'artist' => 'Artists',
                'brand' => 'Brands',
                default => 'Tags',
            };

            $breadcrumbs[] = ['name' => $typeLabel, 'url' => null];
            $breadcrumbs[] = ['name' => $activeTag->name, 'url' => null];
        }

        $pageTitle = $activeCategory?->meta_title
            ?: ($activeCategory ? $activeCategory->name.' | Shop | GoonsGear' : null);

        if ($pageTitle === null && $activeTag !== null) {
            $pageTitle = $activeTag->meta_title ?: $activeTag->name.' | Shop | GoonsGear';
        }

        $pageTitle ??= 'Official SnowGoons Merchandise & Vinyl | GoonsGear Shop';

        $pageDescription = $activeCategory?->meta_description
            ?: (strip_tags((string) $activeCategory?->description) ?: null);

        if ($pageDescription === null && $activeTag !== null) {
            $pageDescription = $activeTag->meta_description
                ?: (strip_tags((string) $activeTag->description)
                    ?: 'Browse '.$activeTag->name.' products on GoonsGear — official merchandise, vinyl, and exclusive drops from SnowGoons.');
        }

        $pageDescription ??= 'Shop official SnowGoons merchandise, limited edition vinyl, exclusive drops, and hip-hop apparel. Worldwide shipping from the legendary production group.';

        $canonicalUrl = match (true) {
            $activeCategory !== null => route('shop.category', $activeCategory),
            $activeTag !== null => match ($activeTag->type) {
                'artist' => route('shop.artist', $activeTag),
                'brand' => route('shop.brand', $activeTag),
                default => route('shop.tag', $activeTag),
            },
            $showCatalog => route('shop.catalog'),
            default => route('shop.index'),
        };

        return view('shop.index', [
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
            'showCatalog' => $showCatalog || $activeCategory !== null || $activeTag !== null,
            'breadcrumbs' => $breadcrumbs,
            'seo' => [
                'title' => $pageTitle,
                'description' => $pageDescription,
                'canonical_url' => $canonicalUrl,
                'og_image' => asset('images/hero-goonsgear.jpg'),
            ],
        ]);
    }
}

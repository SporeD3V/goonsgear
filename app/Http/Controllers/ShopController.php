<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesProductDisplay;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\StockAlertSubscription;
use App\Models\Tag;
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

        // Build breadcrumbs: Home > Category > Product
        $breadcrumbs = [['name' => 'Home', 'url' => route('shop.index')]];

        $primaryCategory = $product->primaryCategory;

        if ($primaryCategory !== null) {
            if ($primaryCategory->parent_id !== null) {
                $parentCategory = $primaryCategory->parent ?? Category::find($primaryCategory->parent_id);

                if ($parentCategory !== null) {
                    $breadcrumbs[] = ['name' => $parentCategory->name, 'url' => route('shop.category', $parentCategory)];
                }
            }

            $breadcrumbs[] = ['name' => $primaryCategory->name, 'url' => route('shop.category', $primaryCategory)];
        }

        $breadcrumbs[] = ['name' => $product->name, 'url' => null];

        return view('shop.show', [
            'product' => $product,
            'variantsWithStockState' => $variantsWithStockState,
            'variantSelectorData' => $variantSelectorData,
            'activeStockAlertVariantIds' => $activeStockAlertVariantIds,
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
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

    private function renderIndex(Request $request, ?Category $forcedCategory = null, ?Tag $forcedTag = null): View
    {
        $activeCategory = $forcedCategory;
        $activeTag = $forcedTag;

        // Build breadcrumbs
        $breadcrumbs = [['name' => 'Home', 'url' => route('shop.index')]];

        if ($activeCategory !== null) {
            if ($activeCategory->parent_id !== null) {
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
                'custom' => 'Tags',
            };

            $breadcrumbs[] = ['name' => $typeLabel, 'url' => null];
            $breadcrumbs[] = ['name' => $activeTag->name, 'url' => null];
        }

        $pageTitle = $activeCategory?->meta_title
            ?: ($activeCategory ? $activeCategory->name.' | Shop | GoonsGear' : null);

        if ($pageTitle === null && $activeTag !== null) {
            $pageTitle = $activeTag->name.' | Shop | GoonsGear';
        }

        $pageTitle ??= 'Shop | GoonsGear';

        $pageDescription = $activeCategory?->meta_description
            ?: (strip_tags((string) $activeCategory?->description) ?: null);

        if ($pageDescription === null && $activeTag !== null) {
            $pageDescription = strip_tags((string) $activeTag->description)
                ?: 'Browse '.$activeTag->name.' products on GoonsGear.';
        }

        $pageDescription ??= 'Browse active GoonsGear products by category, newest arrivals, and price.';

        return view('shop.index', [
            'activeCategory' => $activeCategory,
            'activeTag' => $activeTag,
            'breadcrumbs' => $breadcrumbs,
            'seo' => [
                'title' => $pageTitle,
                'description' => $pageDescription,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $primaryMedia = $product->media->first();

        $seo = [
            'title' => $product->meta_title ?: $product->name.' | GoonsGear',
            'description' => $product->meta_description ?: ($product->excerpt ?: 'Shop '.$product->name.' at GoonsGear.'),
            'canonical_url' => route('shop.show', $product),
            'og_image' => $primaryMedia ? route('media.show', ['path' => $primaryMedia->path]) : null,
        ];

        return view('shop.show', [
            'product' => $product,
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
                    ->where('is_primary', true)
                    ->select(['id', 'product_id', 'path', 'alt_text']),
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select(['id', 'product_id', 'price'])
                    ->orderBy('price'),
            ])
            ->select(['id', 'primary_category_id', 'name', 'slug', 'excerpt'])
            ->limit(8)
            ->get()
            ->map(function ($product) {
                $media = $product->media->first();
                $minPrice = $product->variants->min('price');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'excerpt' => $product->excerpt,
                    'category' => $product->primaryCategory?->name,
                    'price' => $minPrice !== null ? (float) $minPrice : null,
                    'image' => $media ? route('media.show', ['path' => $media->path]) : null,
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
        $sort = $request->string('sort')->trim()->toString();
        $sort = in_array($sort, ['newest', 'name_asc', 'name_desc', 'price_asc', 'price_desc'], true) ? $sort : 'newest';

        $shopCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'meta_title', 'meta_description']);

        $activeCategory = $forcedCategory;

        if ($activeCategory === null && $categorySlug !== '') {
            $activeCategory = $shopCategories->firstWhere('slug', $categorySlug);
        }

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
            ->with([
                'primaryCategory:id,name,slug',
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
            ->paginate(12)
            ->withQueryString();

        $pageTitle = $activeCategory?->meta_title ?: ($activeCategory ? $activeCategory->name.' | Shop | GoonsGear' : 'Shop | GoonsGear');
        $pageDescription = $activeCategory?->meta_description ?: ($activeCategory?->description ?: 'Browse active GoonsGear products by category, newest arrivals, and price.');

        return view('shop.index', [
            'products' => $products,
            'shopCategories' => $shopCategories,
            'activeCategory' => $activeCategory,
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'sort' => $sort,
            ],
            'seo' => [
                'title' => $pageTitle,
                'description' => $pageDescription,
            ],
        ]);
    }
}

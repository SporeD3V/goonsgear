<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $categorySlug = $request->string('category')->trim()->toString();
        $sort = $request->string('sort')->trim()->toString();
        $sort = in_array($sort, ['newest', 'name_asc', 'name_desc', 'price_asc', 'price_desc'], true) ? $sort : 'newest';

        $shopCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

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
            ])
            ->when($sort === 'newest', fn ($query) => $query->latest('id'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('min_active_variant_price IS NULL')->orderBy('min_active_variant_price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('min_active_variant_price')->orderByRaw('min_active_variant_price IS NULL'))
            ->paginate(12)
            ->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'shopCategories' => $shopCategories,
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
                'sort' => $sort,
            ],
        ]);
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

        return view('shop.show', [
            'product' => $product,
        ]);
    }
}

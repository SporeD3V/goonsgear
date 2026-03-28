<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        /** @var LengthAwarePaginator<int, Product> $products */
        $products = Product::query()
            ->where('status', 'active')
            ->with([
                'primaryCategory:id,name',
                'media' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('shop.index', [
            'products' => $products,
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

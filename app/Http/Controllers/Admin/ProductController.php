<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $products = Product::query()
            ->with(['primaryCategory:id,name'])
            ->withCount(['variants', 'media'])
            ->latest('id')
            ->paginate(20);

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.products.create', [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_preorder'] = $request->boolean('is_preorder');

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);

        $product = Product::query()->create($validated);
        $product->categories()->sync($categoryIds);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Product $product): View
    {
        $product->load([
            'categories:id',
            'variants' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            'media' => fn ($query) => $query
                ->with('variant:id,name')
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_preorder'] = $request->boolean('is_preorder');

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);
        unset($validated['media_files']);
        unset($validated['media_alt_text']);

        $product->update($validated);
        $product->categories()->sync($categoryIds);

        $uploadedMediaFiles = $request->file('media_files', []);
        $mediaAltText = $request->string('media_alt_text')->trim()->toString();
        $mediaVariantId = $request->integer('media_variant_id');
        $mediaVariant = null;

        if ($mediaVariantId > 0) {
            $mediaVariant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->find($mediaVariantId);
        }

        if ($uploadedMediaFiles !== []) {
            $nextPosition = (int) ($product->media()->max('position') ?? -1) + 1;
            $hasPrimaryMedia = $product->media()->where('is_primary', true)->exists();

            foreach ($uploadedMediaFiles as $index => $uploadedMediaFile) {
                $storedPath = $this->storeMediaFile(
                    $product,
                    $uploadedMediaFile,
                    $mediaVariant,
                    $index
                );

                ProductMedia::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $mediaVariant?->id,
                    'disk' => 'public',
                    'path' => $storedPath,
                    'mime_type' => $uploadedMediaFile->getMimeType(),
                    'alt_text' => $mediaAltText !== '' ? $mediaAltText : null,
                    'is_primary' => ! $hasPrimaryMedia && $index === 0,
                    'position' => $nextPosition + $index,
                ]);
            }
        }

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product updated successfully.');
    }

    private function storeMediaFile(Product $product, UploadedFile $uploadedMediaFile, ?ProductVariant $variant, int $index): string
    {
        $mediaDirectory = 'products/'.Str::slug($product->slug).'/gallery';
        $originalName = pathinfo($uploadedMediaFile->getClientOriginalName(), PATHINFO_FILENAME);
        $seoBaseName = Str::slug((string) $originalName);
        $seoBaseName = $seoBaseName !== '' ? $seoBaseName : 'media';
        $variantPrefix = $variant instanceof ProductVariant
            ? 'variant-'.Str::slug($variant->name).'-'
            : 'product-';
        $extension = strtolower((string) ($uploadedMediaFile->getClientOriginalExtension() ?: $uploadedMediaFile->extension() ?: 'bin'));
        $filename = $variantPrefix.now()->format('YmdHis').'-'.$index.'-'.$seoBaseName.'.'.$extension;

        return $uploadedMediaFile->storeAs($mediaDirectory, $filename, 'public');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted successfully.');
    }
}

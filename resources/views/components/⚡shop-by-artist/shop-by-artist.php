<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public string $mode = 'artist';

    public string $search = '';

    public function updatedMode(): void
    {
        $this->search = '';
    }

    public function render(): View
    {
        /** @var Collection<int, Tag> $carouselTags */
        $carouselTags = Tag::query()
            ->where('type', 'artist')
            ->where('is_active', true)
            ->where('show_on_homepage', true)
            ->whereNotNull('logo_path')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'logo_path', 'type']);

        $search = trim($this->search);

        /** @var Collection<int, Tag> $searchResults */
        $searchResults = $search !== '' && $this->mode === 'artist'
            ? Tag::query()
                ->where('type', 'artist')
                ->where('is_active', true)
                ->where('name', 'like', '%'.$search.'%')
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'slug', 'type'])
            : collect();

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->where('slug', '!=', 'sale')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Category $category): Category {
                $categoryIds = Category::where('parent_id', $category->id)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->push($category->id)
                    ->all();

                /** @var ProductMedia|null $media */
                $media = ProductMedia::query()
                    ->whereHas('product', fn ($q) => $q
                        ->whereIn('primary_category_id', $categoryIds)
                        ->where('status', 'active'))
                    ->where('is_primary', true)
                    ->inRandomOrder()
                    ->first();

                $category->setAttribute('cover_url', $media
                    ? route('media.show', ['path' => $media->getGalleryPath()])
                    : null);

                $category->setAttribute('product_count', Product::whereIn('primary_category_id', $categoryIds)
                    ->where('status', 'active')
                    ->count());

                return $category;
            });

        return view('components.⚡shop-by-artist.shop-by-artist', [
            'carouselTags' => $carouselTags,
            'searchResults' => $searchResults,
            'categories' => $categories,
        ]);
    }
};

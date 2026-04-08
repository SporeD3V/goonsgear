<?php

use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public string $type = 'artist';

    public string $search = '';

    public function updatedType(): void
    {
        $this->search = '';
    }

    public function render(): View
    {
        /** @var Collection<int, Tag> $carouselTags */
        $carouselTags = Tag::query()
            ->where('type', $this->type)
            ->where('is_active', true)
            ->where('show_on_homepage', true)
            ->whereNotNull('logo_path')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo_path', 'type']);

        $search = trim($this->search);

        /** @var Collection<int, Tag> $searchResults */
        $searchResults = $search !== ''
            ? Tag::query()
                ->where('type', $this->type)
                ->where('is_active', true)
                ->where('name', 'like', '%'.$search.'%')
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'slug', 'type'])
            : collect();

        $hasBrands = Tag::query()
            ->where('type', 'brand')
            ->where('is_active', true)
            ->exists();

        return view('components.⚡shop-by-artist.shop-by-artist', [
            'carouselTags' => $carouselTags,
            'searchResults' => $searchResults,
            'hasBrands' => $hasBrands,
        ]);
    }
};

<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillSeoData extends Command
{
    protected $signature = 'app:backfill-seo-data';

    protected $description = 'Backfill empty slugs on products/categories/tags and empty alt text on product media';

    public function handle(): int
    {
        $this->backfillSlugs();
        $this->backfillAltText();

        $this->info('SEO backfill complete.');

        return self::SUCCESS;
    }

    private function backfillSlugs(): void
    {
        $this->info('Backfilling empty slugs...');

        $productCount = 0;
        Product::whereNull('slug')->orWhere('slug', '')->each(function (Product $product) use (&$productCount) {
            $product->slug = $this->uniqueSlug(Product::class, $product->name, $product->id);
            $product->saveQuietly();
            $productCount++;
        });
        $this->line("  Products: {$productCount} updated");

        $categoryCount = 0;
        Category::whereNull('slug')->orWhere('slug', '')->each(function (Category $category) use (&$categoryCount) {
            $category->slug = $this->uniqueSlug(Category::class, $category->name, $category->id);
            $category->saveQuietly();
            $categoryCount++;
        });
        $this->line("  Categories: {$categoryCount} updated");

        $tagCount = 0;
        Tag::whereNull('slug')->orWhere('slug', '')->each(function (Tag $tag) use (&$tagCount) {
            $tag->slug = $this->uniqueSlug(Tag::class, $tag->name, $tag->id);
            $tag->saveQuietly();
            $tagCount++;
        });
        $this->line("  Tags: {$tagCount} updated");
    }

    private function backfillAltText(): void
    {
        $this->info('Backfilling empty alt text on product media...');

        $count = 0;
        ProductMedia::whereNull('alt_text')
            ->orWhere('alt_text', '')
            ->with('product:id,name')
            ->each(function (ProductMedia $media) use (&$count) {
                /** @var Product|null $product */
                $product = $media->product;

                if ($product?->name) {
                    $media->alt_text = $product->name;
                    $media->saveQuietly();
                    $count++;
                }
            });

        $this->line("  Product media: {$count} updated");
    }

    /**
     * @param  class-string  $modelClass
     */
    private function uniqueSlug(string $modelClass, ?string $name, ?int $excludeId): string
    {
        $slug = Str::slug((string) $name);

        if ($slug === '') {
            $slug = 'item';
        }

        $query = $modelClass::where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if (! $query->exists()) {
            return $slug;
        }

        $suffix = 2;

        while ($modelClass::where('slug', "{$slug}-{$suffix}")->exists()) {
            $suffix++;
        }

        return "{$slug}-{$suffix}";
    }
}

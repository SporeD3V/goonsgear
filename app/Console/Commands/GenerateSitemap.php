<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'app:generate-sitemap';

    protected $description = 'Generate the sitemap.xml file for search engines';

    public function handle(): int
    {
        $this->info('Generating sitemap...');

        $sitemap = Sitemap::create();

        $this->addStaticPages($sitemap);
        $this->addProducts($sitemap);
        $this->addCategories($sitemap);
        $this->addTags($sitemap);

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $count = count($sitemap->getTags());
        $this->info("Sitemap generated with {$count} URLs.");

        return self::SUCCESS;
    }

    private function addStaticPages(Sitemap $sitemap): void
    {
        $sitemap->add(
            Url::create('/')
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
        );
    }

    private function addProducts(Sitemap $sitemap): void
    {
        Product::query()
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->each(function (Product $product) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('shop.show', $product))
                        ->setLastModificationDate($product->updated_at)
                        ->setPriority(0.8)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                );
            });
    }

    private function addCategories(Sitemap $sitemap): void
    {
        Category::query()
            ->where('is_active', true)
            ->whereHas('products')
            ->orderBy('id')
            ->each(function (Category $category) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('shop.category', $category))
                        ->setLastModificationDate($category->updated_at)
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                );
            });
    }

    private function addTags(Sitemap $sitemap): void
    {
        Tag::query()
            ->where('is_active', true)
            ->whereHas('products')
            ->orderBy('id')
            ->each(function (Tag $tag) use ($sitemap) {
                $routeName = match ($tag->type) {
                    'brand' => 'shop.brand',
                    'custom' => 'shop.tag',
                    default => 'shop.artist',
                };

                $sitemap->add(
                    Url::create(route($routeName, $tag))
                        ->setLastModificationDate($tag->updated_at)
                        ->setPriority(0.6)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                );
            });
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSeoMeta extends Command
{
    protected $signature = 'app:generate-seo-meta {--force : Overwrite existing meta data}';

    protected $description = 'Generate SEO meta titles and descriptions for products, categories, and tags. Also links identified products to artist tags.';

    /**
     * Artist tag associations: product IDs mapped to artist tag slugs.
     *
     * @var array<string, list<int>>
     */
    private const ARTIST_LINKS = [
        'epmd' => [491, 232, 256, 231, 258, 21, 233],
        'wu-tang-clan' => [260, 992],
        'nas' => [323, 522, 688, 872, 871],
        'atcq-a-tribe-called-quest' => [990],
        'mf-doom' => [275, 269, 845, 358, 297, 869, 751, 300, 301, 638],
        'ras-kass' => [373, 51, 371, 96, 885, 564, 799],
    ];

    /**
     * Readable category labels for meta descriptions.
     *
     * @var array<string, string>
     */
    private const CATEGORY_LABELS = [
        'vinyl' => 'vinyl record',
        'cds' => 'CD',
        'tapes' => 'cassette tape',
        'tshirts' => 'shirt',
        'hoodies' => 'hoodie',
        'hats' => 'hat',
        'pants' => 'pants',
        'shorts' => 'shorts',
        'wu-wear' => 'socks',
        'accessories' => 'accessory',
        'sale' => 'sale item',
    ];

    public function handle(): int
    {
        $this->linkArtistTags();
        $this->generateProductMeta();
        $this->generateCategoryMeta();
        $this->generateTagMeta();

        $this->info('SEO meta generation complete.');

        return self::SUCCESS;
    }

    private function linkArtistTags(): void
    {
        $this->info('Linking products to artist tags...');
        $linked = 0;

        foreach (self::ARTIST_LINKS as $tagSlug => $productIds) {
            $tag = Tag::where('slug', $tagSlug)->where('type', 'artist')->first();

            if ($tag === null) {
                $this->warn("  Artist tag '{$tagSlug}' not found, skipping.");

                continue;
            }

            foreach ($productIds as $productId) {
                $product = Product::find($productId);

                if ($product === null) {
                    continue;
                }

                if (! $product->tags()->where('tags.id', $tag->id)->exists()) {
                    $product->tags()->attach($tag->id);
                    $linked++;
                }
            }

            $this->line("  {$tag->name}: linked ".count($productIds).' products');
        }

        $this->line("  Total new links: {$linked}");
    }

    private function generateProductMeta(): void
    {
        $this->info('Generating product meta...');

        $force = $this->option('force');
        $updated = 0;

        Product::with(['tags', 'categories'])->each(function (Product $product) use ($force, &$updated) {
            $changed = false;

            if ($force || empty($product->meta_title)) {
                $product->meta_title = $this->buildProductTitle($product);
                $changed = true;
            }

            if ($force || empty($product->meta_description)) {
                $product->meta_description = $this->buildProductDescription($product);
                $changed = true;
            }

            if ($changed) {
                $product->saveQuietly();
                $updated++;
            }
        });

        $this->line("  Products updated: {$updated}");
    }

    private function buildProductTitle(Product $product): string
    {
        $name = $product->name;
        $suffix = ' | GoonsGear';

        // If name fits with suffix within ~60 chars, use it
        if (Str::length($name.$suffix) <= 60) {
            return $name.$suffix;
        }

        // Truncate name to fit
        return Str::limit($name, 60 - Str::length($suffix), '…').$suffix;
    }

    private function buildProductDescription(Product $product): string
    {
        $name = $product->name;
        $artists = $product->tags->where('type', 'artist')->pluck('name');
        $categories = $product->categories->pluck('slug');

        // Determine format label from primary category
        $formatLabel = 'merchandise';
        foreach ($categories as $catSlug) {
            if (isset(self::CATEGORY_LABELS[$catSlug])) {
                $formatLabel = self::CATEGORY_LABELS[$catSlug];

                break;
            }
        }

        // Build description parts
        $parts = [];

        if ($artists->isNotEmpty()) {
            $artistList = $artists->implode(' & ');
            $parts[] = "Shop {$name} by {$artistList} at GoonsGear.";
        } else {
            $parts[] = "Shop {$name} at GoonsGear.";
        }

        // Add format-specific flavor
        $genreTags = $product->tags->whereIn('type', ['custom'])->pluck('name');
        $genre = $genreTags->first() ?: 'hip-hop';

        $parts[] = match (true) {
            Str::contains($formatLabel, 'vinyl') => "Limited edition {$genre} vinyl pressing with worldwide shipping.",
            Str::contains($formatLabel, 'CD') => "Official {$genre} CD release with worldwide shipping.",
            Str::contains($formatLabel, 'cassette') => "Official {$genre} cassette tape with worldwide shipping.",
            Str::contains($formatLabel, 'shirt') => "Premium quality {$genre} streetwear tee with worldwide shipping.",
            Str::contains($formatLabel, 'hoodie') => "Premium quality {$genre} streetwear hoodie with worldwide shipping.",
            Str::contains($formatLabel, 'hat') => "Official {$genre} headwear with worldwide shipping.",
            Str::contains($formatLabel, 'socks') => "Official {$genre} socks with worldwide shipping.",
            Str::contains($formatLabel, 'pants') => "Premium {$genre} streetwear pants with worldwide shipping.",
            Str::contains($formatLabel, 'shorts') => "Premium {$genre} streetwear shorts with worldwide shipping.",
            default => "Official {$genre} merchandise with worldwide shipping.",
        };

        $desc = implode(' ', $parts);

        // Ensure within 120-160 range
        if (Str::length($desc) > 160) {
            $desc = Str::limit($desc, 157, '...');
        }

        return $desc;
    }

    private function generateCategoryMeta(): void
    {
        $this->info('Generating category meta...');

        $force = $this->option('force');
        $updated = 0;

        Category::withCount('products')->each(function (Category $category) use ($force, &$updated) {
            $changed = false;
            $name = $category->name;

            if ($force || empty($category->meta_title)) {
                $title = "{$name} - Hip Hop Merchandise | GoonsGear";
                if (Str::length($title) > 60) {
                    $title = "{$name} | GoonsGear";
                }
                $category->meta_title = $title;
                $changed = true;
            }

            if ($force || empty($category->meta_description)) {
                $count = $category->products_count;
                $category->meta_description = match ($category->slug) {
                    'vinyl' => "Browse {$count}+ hip-hop vinyl records at GoonsGear. Limited pressings, colored vinyl & exclusive releases from SnowGoons artists. Worldwide shipping.",
                    'cds' => "Shop {$count}+ hip-hop CDs at GoonsGear. Official album releases, mixtapes & exclusive compilations from SnowGoons and independent artists.",
                    'tshirts' => "Shop {$count}+ hip-hop t-shirts at GoonsGear. Premium streetwear tees featuring SnowGoons artists and classic hip-hop designs. Worldwide shipping.",
                    'hoodies' => "Browse {$count}+ hip-hop hoodies at GoonsGear. Premium quality streetwear from SnowGoons artists and hip-hop legends. Worldwide shipping.",
                    'hats' => "Shop {$count}+ hip-hop hats at GoonsGear. Snapbacks, bucket hats & fitted caps featuring SnowGoons artists and classic designs. Worldwide shipping.",
                    'wu-wear' => "Shop {$count}+ hip-hop socks at GoonsGear. Premium quality socks featuring iconic hip-hop artists and designs. Worldwide shipping.",
                    'tapes' => "Browse {$count}+ hip-hop cassette tapes at GoonsGear. Limited edition tape releases from SnowGoons and independent artists. Worldwide shipping.",
                    'accessories' => "Shop {$count}+ hip-hop accessories at GoonsGear. Collectibles, stickers, adapters and exclusive merchandise. Worldwide shipping.",
                    'pants' => "Shop {$count}+ hip-hop pants at GoonsGear. Premium streetwear denim and joggers from Tribal Gear and more. Worldwide shipping.",
                    'shorts' => "Shop {$count}+ hip-hop shorts at GoonsGear. Premium streetwear shorts from SnowGoons artists and hip-hop brands. Worldwide shipping.",
                    'sale' => "Grab deals on {$count}+ discounted hip-hop items at GoonsGear. Sale vinyl, apparel, and accessories from SnowGoons artists. While stocks last.",
                    default => "Browse {$name} products at GoonsGear. Official hip-hop merchandise from SnowGoons artists and independent hip-hop legends. Worldwide shipping.",
                };
                $changed = true;
            }

            if ($changed) {
                $category->saveQuietly();
                $updated++;
            }
        });

        $this->line("  Categories updated: {$updated}");
    }

    private function generateTagMeta(): void
    {
        $this->info('Generating tag meta...');

        $force = $this->option('force');
        $updated = 0;

        Tag::withCount('products')->each(function (Tag $tag) use ($force, &$updated) {
            $changed = false;
            $name = $tag->name;

            if ($force || empty($tag->meta_title)) {
                $suffix = Str::contains($name, 'Hip Hop') ? 'Collection' : 'Hip Hop Collection';
                $tag->meta_title = match ($tag->type) {
                    'artist' => Str::limit("{$name} Merchandise & Music | GoonsGear", 60, '…'),
                    'brand' => Str::limit("{$name} Collection | GoonsGear", 60, '…'),
                    default => Str::limit("{$name} {$suffix} | GoonsGear", 60, '…'),
                };
                $changed = true;
            }

            if ($force || empty($tag->meta_description)) {
                $count = $tag->products_count;
                $tag->meta_description = match ($tag->type) {
                    'artist' => "Shop {$count}+ {$name} items at GoonsGear. Official merch, vinyl records, CDs, and apparel from the SnowGoons hip-hop merchandise store.",
                    'brand' => "Browse the {$name} collection at GoonsGear. Premium hip-hop streetwear, apparel, and accessories. Worldwide shipping.",
                    default => "Explore {$count}+ {$name} vinyl, CDs, apparel and accessories at GoonsGear. Authentic hip-hop merchandise with worldwide shipping.",
                };
                $changed = true;
            }

            if ($changed) {
                $tag->saveQuietly();
                $updated++;
            }
        });

        $this->line("  Tags updated: {$updated}");
    }
}

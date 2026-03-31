<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignVariantTypes extends Command
{
    protected $signature = 'variants:assign-types {--dry-run : Preview changes without saving}';

    protected $description = 'Assign variant types (size/color/custom) using legacy WP data context';

    private array $stats = [
        'size' => 0,
        'color' => 0,
        'custom' => 0,
        'uncertain' => 0,
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be saved');
        }

        $variants = ProductVariant::with('product.primaryCategory')->get();
        $this->info("Processing {$variants->count()} variants...");
        
        $uncertain = [];
        $progressBar = $this->output->createProgressBar($variants->count());

        foreach ($variants as $variant) {
            $type = $this->detectVariantType($variant);
            
            if ($type === 'uncertain') {
                $uncertain[] = [
                    'id' => $variant->id,
                    'product' => $variant->product->name,
                    'variant' => $variant->name,
                    'category' => $variant->product->primaryCategory?->name ?? 'None',
                ];
                $this->stats['uncertain']++;
            } else {
                $this->stats[$type]++;
                
                if (!$dryRun) {
                    $variant->update(['variant_type' => $type]);
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✓ Assignment complete!');
        $this->table(
            ['Type', 'Count'],
            [
                ['Size', $this->stats['size']],
                ['Color', $this->stats['color']],
                ['Custom', $this->stats['custom']],
                ['Uncertain (needs review)', $this->stats['uncertain']],
            ]
        );

        if (!empty($uncertain)) {
            $this->newLine();
            $this->warn('Uncertain variants (defaulted to custom):');
            $this->table(
                ['ID', 'Product', 'Variant Name', 'Category'],
                $uncertain
            );
        }

        return 0;
    }

    private function detectVariantType(ProductVariant $variant): string
    {
        $name = trim($variant->name);
        $productName = $variant->product->name ?? '';
        $categoryName = $variant->product->primaryCategory?->name ?? '';

        // Try to get WordPress attribute taxonomy from legacy DB
        $wpAttributeType = $this->getWpAttributeType($variant);
        
        if ($wpAttributeType) {
            return $wpAttributeType;
        }

        // Fallback to pattern matching (conservative)
        if ($this->isColorVariant($name, $productName, $categoryName)) {
            return 'color';
        }

        if ($this->isSizeVariant($name, $productName, $categoryName)) {
            return 'size';
        }

        return 'custom';
    }

    private function getWpAttributeType(ProductVariant $variant): ?string
    {
        try {
            // Get WordPress variation post via SKU from legacy DB
            $wpVariation = DB::connection('legacy')->table('wp_postmeta')
                ->where('meta_key', '_sku')
                ->where('meta_value', $variant->sku)
                ->first();

            if (!$wpVariation) {
                return null;
            }

            // Get attribute meta for this variation
            $attributes = DB::connection('legacy')->table('wp_postmeta')
                ->where('post_id', $wpVariation->post_id)
                ->where('meta_key', 'like', 'attribute_%')
                ->get();

            foreach ($attributes as $attr) {
                // Extract attribute taxonomy from meta_key (e.g., attribute_pa_size -> pa_size)
                if (preg_match('/attribute_(pa_[^_]+)/', $attr->meta_key, $matches)) {
                    $taxonomy = $matches[1];
                    
                    // Check taxonomy name for type hints
                    if (Str::contains($taxonomy, ['size', 'groesse', 'taille'])) {
                        return 'size';
                    }
                    
                    if (Str::contains($taxonomy, ['color', 'colour', 'farbe', 'couleur'])) {
                        return 'color';
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            // If legacy DB not available, return null to use fallback
            return null;
        }
    }

    private function isSizeVariant(string $name, string $productName, string $categoryName): bool
    {
        if (preg_match('/^(XXS|XS|S|M|L|XL|XXL|XXXL|2XL|3XL|4XL|5XL)$/i', $name)) {
            return true;
        }

        if (preg_match('/^\d+(\.\d+)?$/i', $name)) {
            return true;
        }

        if (preg_match('/(small|medium|large|extra)/i', $name)) {
            return true;
        }

        // Biggie/Smalls ONLY for socks (very specific)
        if (preg_match('/(biggie|smalls)/i', $name)) {
            $isSock = stripos($categoryName, 'sock') !== false || 
                      stripos($productName, 'sock') !== false;
            return $isSock;
        }
        
        // Other size keywords (conservative)
        if (preg_match('/(mini|tiny)/i', $name)) {
            $sizeKeywords = ['shirt', 'tee', 'hoodie', 'jacket'];
            foreach ($sizeKeywords as $keyword) {
                if (stripos($categoryName, $keyword) !== false || stripos($productName, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isColorVariant(string $name, string $productName, string $categoryName): bool
    {
        $colors = [
            'black', 'white', 'red', 'blue', 'green', 'yellow', 'navy', 'gray', 'grey',
            'purple', 'orange', 'pink', 'brown', 'beige', 'tan', 'olive', 'maroon',
            'teal', 'cyan', 'magenta', 'gold', 'silver', 'cream', 'burgundy', 'charcoal',
            'mint', 'coral', 'lavender', 'turquoise', 'indigo', 'crimson', 'khaki',
        ];

        $nameLower = strtolower($name);
        foreach ($colors as $color) {
            if (str_contains($nameLower, $color)) {
                return true;
            }
        }

        if (preg_match('/^#[0-9a-f]{6}$/i', $name)) {
            return true;
        }

        return false;
    }
}

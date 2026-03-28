<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the catalog foundation can represent categories, products, variants, and media.
     */
    public function test_catalog_foundation_tables_and_relationships_work(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('product_variants'));
        $this->assertTrue(Schema::hasTable('product_media'));
        $this->assertTrue(Schema::hasTable('category_product'));

        $this->assertTrue(Schema::hasColumns('products', [
            'primary_category_id',
            'name',
            'slug',
            'status',
            'is_preorder',
        ]));

        $category = Category::factory()->create();
        $product = Product::factory()->for($category, 'primaryCategory')->create();
        $product->categories()->attach($category, ['position' => 1]);

        $variant = ProductVariant::factory()->for($product)->create();
        $media = ProductMedia::factory()->for($product)->create([
            'product_variant_id' => $variant->id,
            'is_primary' => true,
        ]);

        $this->assertTrue($product->primaryCategory->is($category));
        $this->assertCount(1, $product->categories);
        $this->assertCount(1, $product->variants);
        $this->assertCount(1, $product->media);
        $this->assertTrue($media->variant->is($variant));
    }
}

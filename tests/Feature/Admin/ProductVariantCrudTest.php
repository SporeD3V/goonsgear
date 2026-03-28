<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Variants can be created from the product variant admin form.
     */
    public function test_admin_can_create_a_product_variant(): void
    {
        $product = Product::factory()->create();

        $response = $this->post(route('admin.products.variants.store', $product), [
            'name' => 'XL / Black',
            'sku' => 'GG-TEE-XL-BLK',
            'option_values_json' => json_encode(['size' => 'XL', 'color' => 'Black']),
            'price' => '39.90',
            'compare_at_price' => '49.90',
            'track_inventory' => '1',
            'stock_quantity' => 25,
            'allow_backorder' => '0',
            'is_active' => '1',
            'is_preorder' => '0',
            'position' => 1,
        ]);

        $response->assertRedirect(route('admin.products.edit', $product));

        $variant = ProductVariant::query()->where('sku', 'GG-TEE-XL-BLK')->first();

        $this->assertNotNull($variant);
        $this->assertSame($product->id, $variant->product_id);
        $this->assertSame('XL / Black', $variant->name);
        $this->assertSame('39.90', $variant->price);
        $this->assertTrue($variant->track_inventory);
    }

    /**
     * Validation errors are returned for missing required variant fields.
     */
    public function test_variant_creation_requires_name_sku_and_price(): void
    {
        $product = Product::factory()->create();

        $response = $this->from(route('admin.products.variants.create', $product))
            ->post(route('admin.products.variants.store', $product), [
                'name' => '',
                'sku' => '',
                'price' => '',
            ]);

        $response->assertRedirect(route('admin.products.variants.create', $product));
        $response->assertSessionHasErrors(['name', 'sku', 'price']);

        $this->assertDatabaseCount('product_variants', 0);
    }
}

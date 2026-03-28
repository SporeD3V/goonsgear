<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Products can be created and linked to categories from admin.
     */
    public function test_admin_can_create_a_product(): void
    {
        $primaryCategory = Category::factory()->create();
        $secondaryCategory = Category::factory()->create();

        $response = $this->post(route('admin.products.store'), [
            'primary_category_id' => $primaryCategory->id,
            'category_ids' => [$primaryCategory->id, $secondaryCategory->id],
            'name' => 'Goonsgear Tee',
            'slug' => 'goonsgear-tee',
            'status' => 'active',
            'excerpt' => 'A simple shirt.',
            'description' => 'Main description',
            'is_featured' => '1',
            'is_preorder' => '0',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('slug', 'goonsgear-tee')->first();

        $this->assertNotNull($product);
        $this->assertSame($primaryCategory->id, $product->primary_category_id);
        $this->assertTrue($product->is_featured);
        $this->assertFalse($product->is_preorder);
        $this->assertDatabaseCount('category_product', 2);
    }

    /**
     * Required fields are validated during product creation.
     */
    public function test_product_creation_requires_core_fields(): void
    {
        $response = $this->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'name' => '',
                'slug' => '',
                'status' => '',
            ]);

        $response->assertRedirect(route('admin.products.create'));
        $response->assertSessionHasErrors(['name', 'slug', 'status']);

        $this->assertDatabaseCount('products', 0);
    }
}

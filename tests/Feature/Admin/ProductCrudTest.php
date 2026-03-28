<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Duplicate product names and slugs are rejected.
     */
    public function test_product_creation_rejects_duplicate_name_and_slug(): void
    {
        Product::factory()->create([
            'name' => 'Goonsgear Tee',
            'slug' => 'goonsgear-tee',
        ]);

        $response = $this->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'name' => 'Goonsgear Tee',
                'slug' => 'goonsgear-tee',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('admin.products.create'));
        $response->assertSessionHasErrors(['name', 'slug']);
    }

    /**
     * Product media can be uploaded from the product edit form.
     */
    public function test_admin_can_upload_product_media_when_updating_product(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'primary_category_id' => $category->id,
            'slug' => 'upload-media-product',
        ]);
        $variant = $product->variants()->create([
            'name' => 'Black / L',
            'sku' => 'GG-TEE-BLK-L',
            'price' => '39.99',
            'track_inventory' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'is_active' => true,
            'is_preorder' => false,
            'position' => 0,
        ]);

        $response = $this->put(route('admin.products.update', $product), [
            'primary_category_id' => $category->id,
            'category_ids' => [$category->id],
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => 'active',
            'media_files' => [
                UploadedFile::fake()->create('shirt-front-main.jpg', 256, 'image/jpeg'),
                UploadedFile::fake()->create('shirt-spin.mp4', 1024, 'video/mp4'),
            ],
            'media_variant_id' => $variant->id,
            'media_alt_text' => 'Shirt front view',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'disk' => 'public',
            'alt_text' => 'Shirt front view',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'mime_type' => 'video/mp4',
        ]);

        $storedPath = (string) $product->media()->value('path');
        $this->assertTrue(Storage::disk('public')->exists($storedPath));
        $this->assertStringContainsString('products/upload-media-product/gallery/', $storedPath);
        $this->assertStringContainsString('shirt-front-main', $storedPath);
    }
}

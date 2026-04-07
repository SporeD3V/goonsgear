<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

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
            'is_converted' => false,
            'converted_to' => null,
        ]);

        $storedPath = (string) $product->media()->where('mime_type', '!=', 'video/mp4')->value('path');
        $this->assertTrue(Storage::disk('public')->exists($storedPath));
        $this->assertStringContainsString('products/upload-media-product/gallery/', $storedPath);
        $this->assertStringContainsString('shirt-front-main', $storedPath);
        $this->assertNotEmpty(Storage::disk('public')->files('products/upload-media-product/fallback'));

        $imageMedia = $product->media()->where('mime_type', '!=', 'video/mp4')->first();
        $this->assertNotNull($imageMedia);

        if ($imageMedia->is_converted) {
            $this->assertContains($imageMedia->converted_to, ['webp', 'avif']);
        } else {
            $this->assertNull($imageMedia->converted_to);
        }
    }

    /**
     * Admin can mark a media item as primary for a product.
     */
    public function test_admin_can_set_primary_media_for_product(): void
    {
        $product = Product::factory()->create();

        $firstMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'is_primary' => true,
            'position' => 0,
        ]);

        $secondMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'is_primary' => false,
            'position' => 1,
        ]);

        $response = $this->patch(route('admin.products.media.primary', [$product, $secondMedia]));

        $response->assertRedirect(route('admin.products.edit', $product));

        $this->assertDatabaseHas('product_media', [
            'id' => $firstMedia->id,
            'is_primary' => false,
        ]);

        $this->assertDatabaseHas('product_media', [
            'id' => $secondMedia->id,
            'is_primary' => true,
        ]);
    }

    /**
     * Deleting primary media promotes the next media item.
     */
    public function test_deleting_primary_media_promotes_next_media(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();

        $firstMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/sample/gallery/first.jpg',
            'is_primary' => true,
            'position' => 0,
        ]);

        $secondMedia = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => 'products/sample/gallery/second.jpg',
            'is_primary' => false,
            'position' => 1,
        ]);

        Storage::disk('public')->put($firstMedia->path, 'first-content');
        Storage::disk('public')->put($secondMedia->path, 'second-content');

        $response = $this->delete(route('admin.products.media.destroy', [$product, $firstMedia]));

        $response->assertRedirect(route('admin.products.edit', $product));

        $this->assertDatabaseMissing('product_media', [
            'id' => $firstMedia->id,
        ]);

        $this->assertDatabaseHas('product_media', [
            'id' => $secondMedia->id,
            'is_primary' => true,
        ]);

        $this->assertFalse(Storage::disk('public')->exists($firstMedia->path));
    }

    /**
     * Product media can be uploaded during product creation.
     */
    public function test_admin_can_upload_media_when_creating_product(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();

        $response = $this->post(route('admin.products.store'), [
            'primary_category_id' => $category->id,
            'category_ids' => [$category->id],
            'name' => 'Media Upload Tee',
            'slug' => 'media-upload-tee',
            'status' => 'draft',
            'media_files' => [
                UploadedFile::fake()->create('front-view.jpg', 256, 'image/jpeg'),
            ],
            'media_alt_text' => 'Front view of tee',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('slug', 'media-upload-tee')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'disk' => 'public',
            'alt_text' => 'Front view of tee',
            'is_primary' => true,
            'position' => 0,
        ]);

        $storedPath = (string) $product->media()->value('path');
        $this->assertTrue(Storage::disk('public')->exists($storedPath));
        $this->assertStringContainsString('products/media-upload-tee/', $storedPath);
    }

    public function test_admin_can_filter_products_by_sales_stock_and_preorder(): void
    {
        $neverSoldZeroStock = Product::factory()->create([
            'name' => 'Unsold Empty Inventory',
            'slug' => 'unsold-empty-inventory',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $neverSoldZeroStock->id,
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);

        $soldZeroStock = Product::factory()->create([
            'name' => 'Purchased Empty Inventory',
            'slug' => 'purchased-empty-inventory',
        ]);
        $soldVariant = ProductVariant::factory()->create([
            'product_id' => $soldZeroStock->id,
            'stock_quantity' => 0,
            'is_preorder' => false,
        ]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $soldZeroStock->id,
            'product_variant_id' => $soldVariant->id,
            'product_name' => $soldZeroStock->name,
            'variant_name' => $soldVariant->name,
            'sku' => $soldVariant->sku,
        ]);

        $preorderProduct = Product::factory()->create([
            'name' => 'Future Release Product',
            'slug' => 'future-release-product',
            'is_preorder' => true,
            'preorder_available_from' => now()->addWeek(),
        ]);
        ProductVariant::factory()->create([
            'product_id' => $preorderProduct->id,
            'stock_quantity' => 10,
            'is_preorder' => true,
            'preorder_available_from' => now()->addWeek(),
        ]);

        $response = Livewire::test('admin.product-manager')
            ->set('filterSales', 'never_sold')
            ->set('filterStock', 'zero_stock');

        $response->assertSeeText('Unsold Empty Inventory');
        $response->assertDontSeeText('Purchased Empty Inventory');
        $response->assertDontSeeText('Future Release Product');

        $preorderResponse = Livewire::test('admin.product-manager')
            ->set('filterPreorder', 'only_preorder');

        $preorderResponse->assertSeeText('Future Release Product');
        $preorderResponse->assertDontSeeText('Unsold Empty Inventory');
    }
}

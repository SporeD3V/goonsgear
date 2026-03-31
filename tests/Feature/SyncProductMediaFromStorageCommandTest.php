<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncProductMediaFromStorageCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $productsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productsRoot = storage_path('app/public/products');
        File::deleteDirectory($this->productsRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->productsRoot);

        parent::tearDown();
    }

    public function test_it_syncs_media_from_storage_and_skips_derivative_images(): void
    {
        $product = Product::factory()->create([
            'slug' => 'sync-media-product',
            'status' => 'active',
        ]);

        $galleryDir = $this->productsRoot.'/sync-media-product/gallery';
        File::ensureDirectoryExists($galleryDir);

        file_put_contents($galleryDir.'/main-image.webp', 'image');
        file_put_contents($galleryDir.'/main-image-hero-1200x600.webp', 'derivative');
        file_put_contents($galleryDir.'/spin-video.mp4', 'video');

        $this->artisan('media:sync-from-storage')->assertSuccessful();

        $this->assertDatabaseCount('product_media', 2);

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'path' => 'products/sync-media-product/gallery/main-image.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
            'position' => 0,
        ]);

        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'path' => 'products/sync-media-product/gallery/spin-video.mp4',
            'mime_type' => 'video/mp4',
            'is_primary' => false,
            'position' => 1,
        ]);

        $this->assertDatabaseMissing('product_media', [
            'product_id' => $product->id,
            'path' => 'products/sync-media-product/gallery/main-image-hero-1200x600.webp',
        ]);
    }
}

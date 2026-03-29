<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FallbackMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_fallback_media_page_lists_product_and_usage_status(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create([
            'name' => 'Black Hoodie',
            'slug' => 'black-hoodie',
        ]);

        $fallbackPath = 'products/black-hoodie/fallback/product-20260329-0-black-hoodie.png';
        $webpPath = 'products/black-hoodie/gallery/product-20260329-0-black-hoodie.webp';

        Storage::disk('public')->put($fallbackPath, 'fallback-content');
        Storage::disk('public')->put($webpPath, 'webp-content');

        ProductMedia::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => $webpPath,
            'mime_type' => 'image/webp',
            'is_primary' => true,
            'position' => 0,
            'is_converted' => true,
            'converted_to' => 'webp',
        ]);

        $response = $this->get(route('admin.maintenance.fallback-media.index'));

        $response->assertOk();
        $response->assertSee('Black Hoodie');
        $response->assertSee($fallbackPath);
        $response->assertSee('Uses WEBP: yes');
    }

    public function test_admin_can_delete_fallback_file_from_page(): void
    {
        Storage::fake('public');

        Product::factory()->create([
            'slug' => 'black-hoodie',
        ]);

        $fallbackPath = 'products/black-hoodie/fallback/product-20260329-0-black-hoodie.png';
        Storage::disk('public')->put($fallbackPath, 'fallback-content');

        $response = $this->from(route('admin.maintenance.fallback-media.index'))
            ->post(route('admin.maintenance.fallback-media.destroy'), [
                'fallback_path' => $fallbackPath,
            ]);

        $response->assertRedirect(route('admin.maintenance.fallback-media.index'));
        $response->assertSessionHas('status');
        $this->assertFalse(Storage::disk('public')->exists($fallbackPath));
    }

    public function test_admin_can_reconvert_fallback_and_apply_to_media_row(): void
    {
        if (! function_exists('imagewebp') && ! function_exists('imageavif')) {
            $this->markTestSkipped('No supported image conversion extensions are available.');
        }

        Storage::fake('public');

        $product = Product::factory()->create([
            'slug' => 'black-hoodie',
        ]);

        $fallbackPath = 'products/black-hoodie/fallback/product-20260329-0-black-hoodie.png';

        // Valid 1x1 PNG image data.
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn+2QAAAABJRU5ErkJggg==');
        Storage::disk('public')->put($fallbackPath, (string) $pngData);

        $media = ProductMedia::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => $fallbackPath,
            'mime_type' => 'image/png',
            'is_primary' => true,
            'position' => 0,
            'is_converted' => false,
            'converted_to' => null,
        ]);

        $response = $this->from(route('admin.maintenance.fallback-media.index'))
            ->post(route('admin.maintenance.fallback-media.reconvert'), [
                'fallback_path' => $fallbackPath,
            ]);

        $response->assertRedirect(route('admin.maintenance.fallback-media.index'));
        $response->assertSessionHas('status');

        $media->refresh();

        $this->assertTrue($media->is_converted);
        $this->assertContains($media->converted_to, ['webp', 'avif']);
        $this->assertTrue(str_ends_with($media->path, '.'.$media->converted_to));
        $this->assertTrue(Storage::disk('public')->exists($media->path));
    }

    public function test_fallback_media_filters_can_find_missing_optimized_files(): void
    {
        Storage::fake('public');

        Product::factory()->create([
            'name' => 'Black Hoodie',
            'slug' => 'black-hoodie',
        ]);

        $missingOptimizedFallback = 'products/black-hoodie/fallback/product-20260329-0-black-hoodie.png';
        $hasOptimizedFallback = 'products/black-hoodie/fallback/product-20260329-1-black-hoodie.png';
        $hasOptimizedWebp = 'products/black-hoodie/gallery/product-20260329-1-black-hoodie.webp';

        Storage::disk('public')->put($missingOptimizedFallback, 'fallback-content-1');
        Storage::disk('public')->put($hasOptimizedFallback, 'fallback-content-2');
        Storage::disk('public')->put($hasOptimizedWebp, 'webp-content');

        $response = $this->get(route('admin.maintenance.fallback-media.index', [
            'optimization' => 'missing',
        ]));

        $response->assertOk();
        $response->assertSee($missingOptimizedFallback);
        $response->assertDontSee($hasOptimizedFallback);
    }

    public function test_fallback_media_filters_can_find_unknown_product_paths(): void
    {
        Storage::fake('public');

        Product::factory()->create([
            'name' => 'Black Hoodie',
            'slug' => 'black-hoodie',
        ]);

        $knownFallback = 'products/black-hoodie/fallback/product-20260329-0-black-hoodie.png';
        $unknownFallback = 'products/missing-product/fallback/product-20260329-0-missing-product.png';

        Storage::disk('public')->put($knownFallback, 'known-fallback');
        Storage::disk('public')->put($unknownFallback, 'unknown-fallback');

        $response = $this->get(route('admin.maintenance.fallback-media.index', [
            'product_state' => 'unknown',
        ]));

        $response->assertOk();
        $response->assertSee($unknownFallback);
        $response->assertDontSee($knownFallback);
    }
}

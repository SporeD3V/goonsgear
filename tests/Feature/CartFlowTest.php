<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{product: Product, variant: ProductVariant}
     */
    private function createPurchasableVariant(): array
    {
        $product = Product::factory()->create([
            'name' => 'Cart Hoodie',
            'slug' => 'cart-hoodie',
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'CART-HOODIE-L',
            'price' => 89.90,
            'track_inventory' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'is_active' => true,
        ]);

        return [
            'product' => $product,
            'variant' => $variant,
        ];
    }

    public function test_can_add_variant_to_cart(): void
    {
        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $response = $this->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('cart.items.'.$variant->id.'.quantity', 2);
        $response->assertSessionHas('cart.items.'.$variant->id.'.sku', 'CART-HOODIE-L');
    }

    public function test_cart_item_uses_thumbnail_variant_image_when_available(): void
    {
        Storage::fake('public');

        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $media = ProductMedia::factory()->create([
            'product_id' => $fixture['product']->id,
            'disk' => 'public',
            'path' => 'products/cart-hoodie/gallery/main.webp',
            'mime_type' => 'image/webp',
            'is_primary' => true,
            'position' => 0,
        ]);

        Storage::disk('public')->put($media->getThumbnailPath(), 'thumbnail-image-bytes');

        $response = $this->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas(
            'cart.items.'.$variant->id.'.image',
            route('media.show', ['path' => $media->getThumbnailPath()])
        );
    }

    public function test_adding_same_variant_increments_quantity(): void
    {
        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $this->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('cart.items.'.$variant->id.'.quantity', 4);
    }

    public function test_cannot_add_more_than_available_stock_when_backorder_is_disabled(): void
    {
        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $response = $this->post(route('cart.items.store'), [
            'variant_id' => $variant->id,
            'quantity' => 6,
        ]);

        $response->assertSessionHasErrors('cart');
        $this->assertNull(session('cart.items.'.$variant->id));
    }

    public function test_can_update_cart_item_quantity(): void
    {
        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Cart Hoodie',
                    'product_slug' => 'cart-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CART-HOODIE-L',
                    'price' => 89.90,
                    'quantity' => 1,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ]);

        $response = $this->patch(route('cart.items.update', $variant), [
            'quantity' => 4,
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('cart.items.'.$variant->id.'.quantity', 4);
    }

    public function test_can_remove_item_from_cart(): void
    {
        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Cart Hoodie',
                    'product_slug' => 'cart-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CART-HOODIE-L',
                    'price' => 89.90,
                    'quantity' => 1,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ]);

        $response = $this->delete(route('cart.items.destroy', $variant));

        $response->assertRedirect(route('cart.index'));
        $this->assertNull(session('cart.items.'.$variant->id));
    }

    public function test_cart_page_displays_subtotal(): void
    {
        $fixture = $this->createPurchasableVariant();
        $variant = $fixture['variant'];

        $this->withSession([
            'cart.items' => [
                $variant->id => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => 'Cart Hoodie',
                    'product_slug' => 'cart-hoodie',
                    'variant_name' => 'Large',
                    'sku' => 'CART-HOODIE-L',
                    'price' => 89.90,
                    'quantity' => 2,
                    'max_quantity' => 5,
                    'image' => null,
                    'url' => route('shop.show', $fixture['product']),
                ],
            ],
        ]);

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('Cart Hoodie');
        $response->assertSee('&euro;179.80', false);
    }
}

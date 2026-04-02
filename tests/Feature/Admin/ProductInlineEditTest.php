<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductEditHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInlineEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_inline_update_changes_product_name(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name']);

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'New Name',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'value' => 'New Name']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name']);
    }

    public function test_inline_update_changes_slug_and_normalizes_it(): void
    {
        $product = Product::factory()->create(['slug' => 'old-slug']);

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'slug',
            'value' => 'New Slug Value',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'value' => 'new-slug-value']);
    }

    public function test_inline_update_rejects_duplicate_slug(): void
    {
        Product::factory()->create(['slug' => 'taken-slug']);
        $product = Product::factory()->create(['slug' => 'my-slug']);

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'slug',
            'value' => 'taken-slug',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'This slug is already in use.']);
    }

    public function test_inline_update_changes_status(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'status',
            'value' => 'active',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
    }

    public function test_inline_update_rejects_invalid_status(): void
    {
        $product = Product::factory()->create();

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'status',
            'value' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_inline_update_toggles_featured(): void
    {
        $product = Product::factory()->create(['is_featured' => false]);

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'is_featured',
            'value' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($product->fresh()->is_featured);
    }

    public function test_inline_update_rejects_disallowed_field(): void
    {
        $product = Product::factory()->create();

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'description',
            'value' => 'hacked',
        ]);

        $response->assertStatus(422);
    }

    public function test_inline_update_creates_edit_history(): void
    {
        $product = Product::factory()->create(['name' => 'Original']);

        $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'Changed',
        ]);

        $this->assertDatabaseHas('product_edit_histories', [
            'product_id' => $product->id,
            'field' => 'name',
            'old_value' => 'Original',
            'new_value' => 'Changed',
        ]);
    }

    public function test_inline_update_skips_when_value_unchanged(): void
    {
        $product = Product::factory()->create(['name' => 'Same']);

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'Same',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'unchanged' => true]);
        $this->assertDatabaseMissing('product_edit_histories', ['product_id' => $product->id]);
    }

    public function test_revert_restores_previous_value(): void
    {
        $product = Product::factory()->create(['name' => 'Original']);

        $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'Changed',
        ]);

        $response = $this->postJson(route('admin.products.revert-field', $product), [
            'field' => 'name',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'value' => 'Original']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Original']);
    }

    public function test_revert_removes_consumed_history_entry(): void
    {
        $product = Product::factory()->create(['name' => 'V1']);

        $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'V2',
        ]);

        $this->postJson(route('admin.products.revert-field', $product), [
            'field' => 'name',
        ]);

        $this->assertDatabaseMissing('product_edit_histories', [
            'product_id' => $product->id,
            'field' => 'name',
        ]);
    }

    public function test_revert_walks_back_through_multiple_edits(): void
    {
        $product = Product::factory()->create(['name' => 'V1']);

        $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'V2',
        ]);

        $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'V3',
        ]);

        // First revert: V3 -> V2
        $response = $this->postJson(route('admin.products.revert-field', $product), [
            'field' => 'name',
        ]);

        $response->assertJson(['success' => true, 'value' => 'V2', 'has_more_history' => true]);

        // Second revert: V2 -> V1
        $response = $this->postJson(route('admin.products.revert-field', $product), [
            'field' => 'name',
        ]);

        $response->assertJson(['success' => true, 'value' => 'V1', 'has_more_history' => false]);
    }

    public function test_revert_returns_404_when_no_history(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson(route('admin.products.revert-field', $product), [
            'field' => 'name',
        ]);

        $response->assertStatus(404);
    }

    public function test_edit_history_is_pruned_to_ten_entries(): void
    {
        $product = Product::factory()->create(['name' => 'Start']);

        foreach (range(1, 12) as $i) {
            $this->patchJson(route('admin.products.inline-update', $product), [
                'field' => 'name',
                'value' => "Version {$i}",
            ]);
        }

        $this->assertSame(
            10,
            ProductEditHistory::query()
                ->where('product_id', $product->id)
                ->where('field', 'name')
                ->count()
        );
    }

    public function test_admin_products_index_shows_image_column(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $response = $this->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSee('Image', false);
        $response->assertSee('Featured', false);
        $response->assertSee('Stock Alerts', false);
    }

    public function test_guest_cannot_inline_update(): void
    {
        $product = Product::factory()->create();

        // Reset to guest
        auth()->logout();

        $response = $this->patchJson(route('admin.products.inline-update', $product), [
            'field' => 'name',
            'value' => 'Hacked',
        ]);

        $response->assertStatus(401);
    }
}

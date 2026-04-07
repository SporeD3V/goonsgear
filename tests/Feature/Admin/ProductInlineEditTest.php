<?php

namespace Tests\Feature\Admin;

use App\Models\EditHistory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'name', 'New Name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name']);
    }

    public function test_inline_update_changes_slug_and_normalizes_it(): void
    {
        $product = Product::factory()->create(['slug' => 'old-slug']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'slug', 'New Slug Value');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'new-slug-value']);
    }

    public function test_inline_update_rejects_duplicate_slug(): void
    {
        Product::factory()->create(['slug' => 'taken-slug']);
        $product = Product::factory()->create(['slug' => 'my-slug']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'slug', 'taken-slug');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'my-slug']);
    }

    public function test_inline_update_changes_status(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'status', 'active');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
    }

    public function test_inline_update_rejects_invalid_status(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'status', 'invalid');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'draft']);
    }

    public function test_inline_update_toggles_featured(): void
    {
        $product = Product::factory()->create(['is_featured' => false]);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'is_featured', true);

        $this->assertTrue($product->fresh()->is_featured);
    }

    public function test_inline_update_rejects_disallowed_field(): void
    {
        $product = Product::factory()->create(['description' => 'original']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'description', 'hacked');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'description' => 'original']);
    }

    public function test_inline_update_creates_edit_history(): void
    {
        $product = Product::factory()->create(['name' => 'Original']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'name', 'Changed');

        $this->assertDatabaseHas('edit_histories', [
            'editable_type' => Product::class,
            'editable_id' => $product->id,
            'field' => 'name',
            'old_value' => 'Original',
            'new_value' => 'Changed',
        ]);
    }

    public function test_inline_update_skips_when_value_unchanged(): void
    {
        $product = Product::factory()->create(['name' => 'Same']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'name', 'Same');

        $this->assertDatabaseMissing('edit_histories', ['editable_type' => Product::class, 'editable_id' => $product->id]);
    }

    public function test_revert_restores_previous_value(): void
    {
        $product = Product::factory()->create(['name' => 'Original']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'name', 'Changed')
            ->call('revertField', $product->id, 'name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Original']);
    }

    public function test_revert_removes_consumed_history_entry(): void
    {
        $product = Product::factory()->create(['name' => 'V1']);

        Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'name', 'V2')
            ->call('revertField', $product->id, 'name');

        $this->assertDatabaseMissing('edit_histories', [
            'editable_type' => Product::class,
            'editable_id' => $product->id,
            'field' => 'name',
        ]);
    }

    public function test_revert_walks_back_through_multiple_edits(): void
    {
        $product = Product::factory()->create(['name' => 'V1']);

        $component = Livewire::test('admin.product-manager')
            ->call('inlineUpdate', $product->id, 'name', 'V2')
            ->call('inlineUpdate', $product->id, 'name', 'V3');

        // First revert: V3 -> V2
        $component->call('revertField', $product->id, 'name');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'V2']);
        $this->assertTrue(EditHistory::hasHistory($product->fresh(), 'name'));

        // Second revert: V2 -> V1
        $component->call('revertField', $product->id, 'name');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'V1']);
        $this->assertFalse(EditHistory::hasHistory($product->fresh(), 'name'));
    }

    public function test_revert_does_nothing_when_no_history(): void
    {
        $product = Product::factory()->create(['name' => 'Original']);

        Livewire::test('admin.product-manager')
            ->call('revertField', $product->id, 'name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Original']);
    }

    public function test_edit_history_is_pruned_to_ten_entries(): void
    {
        $product = Product::factory()->create(['name' => 'Start']);

        $component = Livewire::test('admin.product-manager');

        foreach (range(1, 12) as $i) {
            $component->call('inlineUpdate', $product->id, 'name', "Version {$i}");
        }

        $this->assertSame(
            10,
            EditHistory::query()
                ->where('editable_type', Product::class)
                ->where('editable_id', $product->id)
                ->where('field', 'name')
                ->count()
        );
    }

    public function test_admin_products_index_shows_image_column(): void
    {
        Product::factory()->create(['status' => 'active']);

        Livewire::test('admin.product-manager')
            ->assertSee('Image', false)
            ->assertSee('Featured', false)
            ->assertSee('Stock Alerts', false);
    }

    public function test_guest_cannot_access_product_manager(): void
    {
        auth()->logout();

        $this->get(route('admin.products.index'))
            ->assertRedirect(route('login'));
    }
}

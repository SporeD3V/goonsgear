<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_admin_can_create_a_category(): void
    {
        Livewire::test('admin.category-manager')
            ->call('openCreate')
            ->set('name', 'Headwear')
            ->set('slug', 'headwear')
            ->set('description', 'Beanies and caps.')
            ->set('is_active', true)
            ->set('sort_order', 1)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('categories', [
            'name' => 'Headwear',
            'slug' => 'headwear',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_category_creation_requires_name_and_slug(): void
    {
        $initialCount = Category::count();

        Livewire::test('admin.category-manager')
            ->call('openCreate')
            ->set('name', '')
            ->set('slug', '')
            ->call('save')
            ->assertHasErrors(['name', 'slug']);

        $this->assertDatabaseCount('categories', $initialCount);
    }

    public function test_category_creation_rejects_duplicate_name_and_slug(): void
    {
        Category::factory()->create([
            'name' => 'Headwear',
            'slug' => 'headwear',
        ]);

        Livewire::test('admin.category-manager')
            ->call('openCreate')
            ->set('name', 'Headwear')
            ->set('slug', 'headwear')
            ->call('save')
            ->assertHasErrors(['name', 'slug']);
    }

    public function test_admin_can_create_category_with_size_type(): void
    {
        Livewire::test('admin.category-manager')
            ->call('openCreate')
            ->set('name', 'Shirts')
            ->set('slug', 'shirts')
            ->set('is_active', true)
            ->set('size_type', 'top')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('categories', [
            'name' => 'Shirts',
            'slug' => 'shirts',
            'size_type' => 'top',
        ]);
    }

    public function test_empty_size_type_is_stored_as_null(): void
    {
        Livewire::test('admin.category-manager')
            ->call('openCreate')
            ->set('name', 'Accessories')
            ->set('slug', 'accessories')
            ->set('is_active', true)
            ->set('size_type', '')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('categories', [
            'name' => 'Accessories',
            'size_type' => null,
        ]);
    }

    public function test_admin_can_update_category_size_type(): void
    {
        $category = Category::factory()->create([
            'name' => 'Socks',
            'slug' => 'socks',
            'size_type' => 'bottom',
        ]);

        Livewire::test('admin.category-manager')
            ->call('openEdit', $category->id)
            ->set('size_type', 'shoe')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'size_type' => 'shoe',
        ]);
    }

    public function test_invalid_size_type_is_rejected(): void
    {
        Livewire::test('admin.category-manager')
            ->call('openCreate')
            ->set('name', 'Test')
            ->set('slug', 'test')
            ->set('size_type', 'invalid')
            ->call('save')
            ->assertHasErrors(['size_type']);
    }
}

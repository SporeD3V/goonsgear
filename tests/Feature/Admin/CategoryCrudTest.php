<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    /**
     * Categories can be created from the admin flow.
     */
    public function test_admin_can_create_a_category(): void
    {
        $response = $this->post(route('admin.categories.store'), [
            'name' => 'Headwear',
            'slug' => 'headwear',
            'description' => 'Beanies and caps.',
            'is_active' => '1',
            'sort_order' => 1,
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Headwear',
            'slug' => 'headwear',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * The form request validates required category fields.
     */
    public function test_category_creation_requires_name_and_slug(): void
    {
        $initialCount = Category::count();

        $response = $this->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => '',
                'slug' => '',
            ]);

        $response->assertRedirect(route('admin.categories.create'));
        $response->assertSessionHasErrors(['name', 'slug']);

        $this->assertDatabaseCount('categories', $initialCount);
    }

    /**
     * Duplicate category names and slugs are rejected.
     */
    public function test_category_creation_rejects_duplicate_name_and_slug(): void
    {
        Category::factory()->create([
            'name' => 'Headwear',
            'slug' => 'headwear',
        ]);

        $response = $this->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => 'Headwear',
                'slug' => 'headwear',
            ]);

        $response->assertRedirect(route('admin.categories.create'));
        $response->assertSessionHasErrors(['name', 'slug']);
    }

    /**
     * Categories can be created with a size_type.
     */
    public function test_admin_can_create_category_with_size_type(): void
    {
        $response = $this->post(route('admin.categories.store'), [
            'name' => 'Shirts',
            'slug' => 'shirts',
            'is_active' => '1',
            'size_type' => 'top',
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Shirts',
            'slug' => 'shirts',
            'size_type' => 'top',
        ]);
    }

    /**
     * Empty size_type is stored as null.
     */
    public function test_empty_size_type_is_stored_as_null(): void
    {
        $response = $this->post(route('admin.categories.store'), [
            'name' => 'Accessories',
            'slug' => 'accessories',
            'is_active' => '1',
            'size_type' => '',
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Accessories',
            'size_type' => null,
        ]);
    }

    /**
     * Size type can be updated on an existing category.
     */
    public function test_admin_can_update_category_size_type(): void
    {
        $category = Category::factory()->create([
            'name' => 'Socks',
            'slug' => 'socks',
            'size_type' => 'bottom',
        ]);

        $response = $this->put(route('admin.categories.update', $category), [
            'name' => 'Socks',
            'slug' => 'socks',
            'is_active' => '1',
            'size_type' => 'shoe',
        ]);

        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'size_type' => 'shoe',
        ]);
    }

    /**
     * Invalid size_type values are rejected.
     */
    public function test_invalid_size_type_is_rejected(): void
    {
        $response = $this->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => 'Test',
                'slug' => 'test',
                'size_type' => 'invalid',
            ]);

        $response->assertRedirect(route('admin.categories.create'));
        $response->assertSessionHasErrors(['size_type']);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

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
        $response = $this->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => '',
                'slug' => '',
            ]);

        $response->assertRedirect(route('admin.categories.create'));
        $response->assertSessionHasErrors(['name', 'slug']);

        $this->assertDatabaseCount('categories', 0);
    }
}

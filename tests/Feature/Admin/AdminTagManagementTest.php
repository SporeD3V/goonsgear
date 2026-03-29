<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTagManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin can create artist/brand tags.
     */
    public function test_admin_can_create_tag(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.tags.store'), [
            'name' => 'Boom Bap Collective',
            'slug' => 'boom-bap-collective',
            'type' => 'artist',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.tags.index'));

        $this->assertDatabaseHas('tags', [
            'name' => 'Boom Bap Collective',
            'slug' => 'boom-bap-collective',
            'type' => 'artist',
            'is_active' => true,
        ]);
    }

    public function test_admin_index_shows_follower_counts_per_tag(): void
    {
        $this->actingAsAdmin();

        $tag = Tag::factory()->create([
            'name' => 'Vinyl Syndicate',
            'type' => 'brand',
        ]);

        TagFollow::factory()->count(2)->create([
            'tag_id' => $tag->id,
        ]);

        $response = $this->get(route('admin.tags.index'));

        $response->assertOk();
        $response->assertSee('Vinyl Syndicate');
        $response->assertSee('2');
    }

    public function test_non_admin_cannot_access_admin_tags(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.tags.index'));

        $response->assertForbidden();
    }
}

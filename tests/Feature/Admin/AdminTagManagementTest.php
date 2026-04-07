<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTagManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_tag(): void
    {
        $this->actingAsAdmin();

        Livewire::test('admin.tag-manager')
            ->call('openCreate')
            ->set('name', 'Boom Bap Collective')
            ->set('slug', 'boom-bap-collective')
            ->set('type', 'artist')
            ->set('is_active', true)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('tags', [
            'name' => 'Boom Bap Collective',
            'slug' => 'boom-bap-collective',
            'type' => 'artist',
            'is_active' => true,
        ]);
    }

    public function test_admin_index_shows_tags(): void
    {
        $this->actingAsAdmin();

        $tag = Tag::factory()->create([
            'name' => 'Vinyl Syndicate',
            'type' => 'brand',
        ]);

        TagFollow::factory()->count(2)->create([
            'tag_id' => $tag->id,
        ]);

        Livewire::test('admin.tag-manager')
            ->assertSee('Vinyl Syndicate')
            ->assertSee('2');
    }

    public function test_non_admin_cannot_access_admin_tags(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.tags.index'));

        $response->assertForbidden();
    }
}

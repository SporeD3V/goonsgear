<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTagFollowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticated users can follow artists/brands with custom preferences.
     */
    public function test_user_can_follow_a_tag_and_set_preferences(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->post(route('account.tag-follows.store'), [
                'tag_id' => $tag->id,
                'notify_new_drops' => '1',
            ]);

        $response->assertRedirect(route('account.index'));

        $this->assertDatabaseHas('tag_follows', [
            'user_id' => $user->id,
            'tag_id' => $tag->id,
            'notify_new_drops' => true,
            'notify_discounts' => false,
        ]);
    }

    public function test_user_can_update_follow_preferences(): void
    {
        $user = User::factory()->create();
        $tagFollow = TagFollow::factory()->create([
            'user_id' => $user->id,
            'notify_new_drops' => true,
            'notify_discounts' => true,
        ]);

        $response = $this->actingAs($user)
            ->patch(route('account.tag-follows.update', $tagFollow), []);

        $response->assertRedirect(route('account.index'));

        $tagFollow->refresh();
        $this->assertFalse($tagFollow->notify_new_drops);
        $this->assertFalse($tagFollow->notify_discounts);
    }

    public function test_user_cannot_update_another_users_follow_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $tagFollow = TagFollow::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->patch(route('account.tag-follows.update', $tagFollow), [
                'notify_new_drops' => '1',
                'notify_discounts' => '1',
            ]);

        $response->assertForbidden();
    }

    public function test_account_page_displays_followed_tags(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create([
            'name' => 'Rhyme Syndicate',
            'type' => 'artist',
        ]);

        TagFollow::factory()->create([
            'user_id' => $user->id,
            'tag_id' => $tag->id,
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('Artist: Rhyme Syndicate');
    }
}

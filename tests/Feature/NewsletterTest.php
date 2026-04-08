<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_newsletter_component(): void
    {
        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertSeeLivewire('newsletter');
    }

    public function test_user_can_subscribe_to_newsletter(): void
    {
        Livewire::test('newsletter')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->call('subscribe')
            ->assertSet('subscribed', true);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_name_and_email_are_required(): void
    {
        Livewire::test('newsletter')
            ->set('name', '')
            ->set('email', '')
            ->call('subscribe')
            ->assertHasErrors(['name', 'email'])
            ->assertSet('subscribed', false);
    }

    public function test_email_must_be_valid(): void
    {
        Livewire::test('newsletter')
            ->set('name', 'Jane')
            ->set('email', 'not-an-email')
            ->call('subscribe')
            ->assertHasErrors(['email'])
            ->assertSet('subscribed', false);
    }

    public function test_already_subscribed_user_sees_success_without_duplicate(): void
    {
        NewsletterSubscriber::factory()->create([
            'email' => 'existing@example.com',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        Livewire::test('newsletter')
            ->set('name', 'Existing User')
            ->set('email', 'existing@example.com')
            ->call('subscribe')
            ->assertSet('subscribed', true);

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_unsubscribed_user_can_resubscribe(): void
    {
        NewsletterSubscriber::factory()->create([
            'email' => 'comeback@example.com',
            'name' => 'Old Name',
            'subscribed_at' => now()->subMonth(),
            'unsubscribed_at' => now()->subWeek(),
        ]);

        Livewire::test('newsletter')
            ->set('name', 'New Name')
            ->set('email', 'comeback@example.com')
            ->call('subscribe')
            ->assertSet('subscribed', true);

        $subscriber = NewsletterSubscriber::where('email', 'comeback@example.com')->first();
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertEquals('New Name', $subscriber->name);
    }
}

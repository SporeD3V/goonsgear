<?php

namespace Tests\Feature;

use App\Models\StockAlertSubscription;
use App\Models\TagFollow;
use App\Models\User;
use App\Policies\StockAlertSubscriptionPolicy;
use App\Policies\TagFollowPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_follow_policy_allows_owner_and_denies_other_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $tagFollow = TagFollow::factory()->create(['user_id' => $owner->id]);
        $policy = new TagFollowPolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->update($owner, $tagFollow));
        $this->assertTrue($policy->delete($owner, $tagFollow));

        $this->assertFalse($policy->update($otherUser, $tagFollow));
        $this->assertFalse($policy->delete($otherUser, $tagFollow));
    }

    public function test_stock_alert_subscription_policy_allows_owner_and_denies_other_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $subscription = StockAlertSubscription::factory()->create(['user_id' => $owner->id]);
        $policy = new StockAlertSubscriptionPolicy;

        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->update($owner, $subscription));
        $this->assertTrue($policy->delete($owner, $subscription));

        $this->assertFalse($policy->update($otherUser, $subscription));
        $this->assertFalse($policy->delete($otherUser, $subscription));
    }
}

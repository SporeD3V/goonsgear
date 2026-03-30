<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_delivery_address_from_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('account.delivery-address.update'), [
            'delivery_phone' => '+49123456789',
            'delivery_country' => 'de',
            'delivery_state' => 'BE',
            'delivery_city' => 'Berlin',
            'delivery_postal_code' => '10115',
            'delivery_street_name' => 'Alexanderplatz',
            'delivery_street_number' => '1A',
            'delivery_apartment_block' => 'B',
            'delivery_entrance' => 'North',
            'delivery_floor' => '4',
            'delivery_apartment_number' => '17',
        ]);

        $response->assertRedirect(route('account.index'));

        $user->refresh();

        $this->assertSame('+49123456789', $user->delivery_phone);
        $this->assertSame('DE', $user->delivery_country);
        $this->assertSame('Berlin', $user->delivery_city);
        $this->assertSame('Alexanderplatz', $user->delivery_street_name);
    }

    public function test_account_page_shows_only_orders_for_user_email(): void
    {
        $user = User::factory()->create(['email' => 'account-owner@example.com']);

        $visibleOrder = Order::factory()->create([
            'order_number' => 'GG-ORDER-111',
            'email' => 'account-owner@example.com',
        ]);

        Order::factory()->create([
            'order_number' => 'GG-ORDER-999',
            'email' => 'someone-else@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('GG-ORDER-111');
        $response->assertDontSee('GG-ORDER-999');
        $response->assertSee('My Orders');

        $this->assertNotNull($visibleOrder);
    }

    public function test_account_page_shows_active_assigned_discount_codes(): void
    {
        $user = User::factory()->create();

        $coupon = Coupon::factory()->create([
            'code' => 'MEMBER15',
            'is_active' => true,
            'is_personal' => true,
            'is_stackable' => true,
        ]);

        $user->coupons()->attach($coupon->id, [
            'usage_limit' => 3,
            'used_count' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('My Discount Codes');
        $response->assertSee('MEMBER15');
        $response->assertSee('2');
    }

    public function test_account_page_still_loads_when_coupon_assignment_table_is_missing(): void
    {
        $user = User::factory()->create();

        Schema::dropIfExists('coupon_user');

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('My Discount Codes');
        $response->assertSee('No active discount codes are assigned to your account.');
    }
}

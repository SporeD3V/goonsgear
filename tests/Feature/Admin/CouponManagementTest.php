<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_admin_coupons_index_lists_existing_coupons(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'WELCOME10',
        ]);

        $response = $this->get(route('admin.coupons.index'));

        $response->assertOk();
        $response->assertSee($coupon->code);
    }

    public function test_admin_can_create_coupon(): void
    {
        $response = $this->post(route('admin.coupons.store'), [
            'code' => 'save20',
            'description' => 'Spring promotion',
            'type' => Coupon::TYPE_FIXED,
            'value' => 20,
            'minimum_subtotal' => 100,
            'usage_limit' => 50,
            'used_count' => 0,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE20',
            'type' => Coupon::TYPE_FIXED,
            'description' => 'Spring promotion',
        ]);
    }

    public function test_admin_can_update_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
        ]);

        $response = $this->patch(route('admin.coupons.update', $coupon), [
            'code' => 'save15',
            'description' => 'Updated promotion',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 15,
            'minimum_subtotal' => 75,
            'usage_limit' => 25,
            'used_count' => 3,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'SAVE15',
            'description' => 'Updated promotion',
            'value' => 15,
            'used_count' => 3,
        ]);
    }
}

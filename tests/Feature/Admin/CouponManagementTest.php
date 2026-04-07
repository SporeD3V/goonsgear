<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        Livewire::test('admin.coupon-manager')
            ->assertSee($coupon->code);
    }

    public function test_admin_can_create_coupon(): void
    {
        Livewire::test('admin.coupon-manager')
            ->call('openCreate')
            ->set('code', 'save20')
            ->set('description', 'Spring promotion')
            ->set('type', Coupon::TYPE_FIXED)
            ->set('value', '20')
            ->set('minimum_subtotal', '100')
            ->set('usage_limit', '50')
            ->set('used_count', 0)
            ->set('is_active', true)
            ->call('save')
            ->assertSet('showModal', false);

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

        Livewire::test('admin.coupon-manager')
            ->call('openEdit', $coupon->id)
            ->set('code', 'save15')
            ->set('description', 'Updated promotion')
            ->set('type', Coupon::TYPE_PERCENT)
            ->set('value', '15')
            ->set('minimum_subtotal', '75')
            ->set('usage_limit', '25')
            ->set('used_count', 3)
            ->set('is_active', true)
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'SAVE15',
            'description' => 'Updated promotion',
            'value' => 15,
            'used_count' => 3,
        ]);
    }

    public function test_personal_coupon_requires_assigned_users(): void
    {
        Livewire::test('admin.coupon-manager')
            ->call('openCreate')
            ->set('code', 'PERSONAL10')
            ->set('description', 'Personal campaign')
            ->set('type', Coupon::TYPE_FIXED)
            ->set('value', '10')
            ->set('is_active', true)
            ->set('is_personal', true)
            ->call('save')
            ->assertHasErrors('assigned_user_ids');
    }

    public function test_product_scope_requires_product_target(): void
    {
        User::factory()->create();
        Product::factory()->create(['status' => 'active']);

        Livewire::test('admin.coupon-manager')
            ->call('openCreate')
            ->set('code', 'SCOPE10')
            ->set('description', 'Scoped campaign')
            ->set('type', Coupon::TYPE_FIXED)
            ->set('value', '10')
            ->set('is_active', true)
            ->set('scope_type', Coupon::SCOPE_PRODUCT)
            ->call('save')
            ->assertHasErrors('scope_product_id');
    }
}

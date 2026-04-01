<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SizeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SizeProfileTest extends TestCase
{
    use RefreshDatabase;

    // ── CRUD Tests ──────────────────────────────────────────────────────

    public function test_user_can_create_self_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.size-profiles.store'), [
            'name' => $user->name,
            'is_self' => '1',
            'top_size' => 'M',
            'bottom_size' => 'L',
            'shoe_size' => '42',
        ]);

        $response->assertRedirect(route('account.index'));

        $this->assertDatabaseHas('size_profiles', [
            'user_id' => $user->id,
            'is_self' => true,
            'top_size' => 'M',
            'bottom_size' => 'L',
            'shoe_size' => '42',
        ]);
    }

    public function test_user_can_create_profile_for_another_person(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.size-profiles.store'), [
            'name' => 'John',
            'is_self' => '0',
            'top_size' => 'XL',
            'bottom_size' => null,
            'shoe_size' => '44',
        ]);

        $response->assertRedirect(route('account.index'));

        $this->assertDatabaseHas('size_profiles', [
            'user_id' => $user->id,
            'name' => 'John',
            'is_self' => false,
            'top_size' => 'XL',
        ]);
    }

    public function test_user_cannot_create_duplicate_self_profile(): void
    {
        $user = User::factory()->create();

        SizeProfile::factory()->self()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('account.size-profiles.store'), [
            'name' => $user->name,
            'is_self' => '1',
            'top_size' => 'S',
        ]);

        $response->assertRedirect(route('account.index'));
        $response->assertSessionHasErrors('is_self');

        $this->assertCount(1, $user->sizeProfiles()->where('is_self', true)->get());
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->create();
        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
        ]);

        $response = $this->actingAs($user)->patch(route('account.size-profiles.update', $profile), [
            'name' => $user->name,
            'top_size' => 'L',
            'bottom_size' => 'L',
            'shoe_size' => '43',
        ]);

        $response->assertRedirect(route('account.index'));

        $profile->refresh();
        $this->assertSame('L', $profile->top_size);
        $this->assertSame('L', $profile->bottom_size);
        $this->assertSame('43', $profile->shoe_size);
    }

    public function test_user_cannot_update_another_users_profile(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $profile = SizeProfile::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->patch(route('account.size-profiles.update', $profile), [
            'name' => 'Hacker',
            'top_size' => 'XXL',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_non_self_profile(): void
    {
        $user = User::factory()->create();
        $profile = SizeProfile::factory()->create([
            'user_id' => $user->id,
            'is_self' => false,
            'name' => 'John',
        ]);

        $response = $this->actingAs($user)->delete(route('account.size-profiles.destroy', $profile));

        $response->assertRedirect(route('account.index'));
        $this->assertDatabaseMissing('size_profiles', ['id' => $profile->id]);
    }

    public function test_user_cannot_delete_another_users_profile(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $profile = SizeProfile::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->delete(route('account.size-profiles.destroy', $profile));

        $response->assertForbidden();
        $this->assertDatabaseHas('size_profiles', ['id' => $profile->id]);
    }

    public function test_store_redirects_to_custom_redirect_when_provided(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.size-profiles.store'), [
            'name' => $user->name,
            'is_self' => '1',
            'top_size' => 'M',
            '_redirect' => '/checkout/success/1',
        ]);

        $response->assertRedirect('/checkout/success/1');
    }

    public function test_account_page_shows_size_profiles_section(): void
    {
        $user = User::factory()->create();

        SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'top_size' => 'M',
            'bottom_size' => 'L',
        ]);

        SizeProfile::factory()->create([
            'user_id' => $user->id,
            'name' => 'John',
            'top_size' => 'XL',
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('Size Profiles');
        $response->assertSee('You');
        $response->assertSee('John');
    }

    // ── Post-order Size Prompt Tests ────────────────────────────────────

    public function test_checkout_success_shows_create_prompt_when_no_self_profile(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);

        $sizeVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['email' => $user->email]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $sizeVariant->id,
            'variant_name' => 'M',
        ]);

        $response = $this->actingAs($user)->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee('You ordered sizes:');
        $response->assertSeeText('M');
        $response->assertSee('Save my sizes');
    }

    public function test_checkout_success_shows_mismatch_prompt_when_sizes_differ(): void
    {
        $user = User::factory()->create();

        SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'S',
            'bottom_size' => 'S',
        ]);

        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'XL',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['email' => $user->email]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'XL',
        ]);

        $response = $this->actingAs($user)->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee('These sizes differ from your saved profile');
        $response->assertSee('Update my sizes');
        $response->assertSee('Add another person');
    }

    public function test_checkout_success_hides_prompt_when_sizes_match_profile(): void
    {
        $user = User::factory()->create();

        SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
        ]);

        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['email' => $user->email]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'M',
        ]);

        $response = $this->actingAs($user)->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertDontSee('You ordered sizes:');
        $response->assertDontSee('Save my sizes');
    }

    public function test_checkout_success_hides_prompt_when_no_sized_variants(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Black',
            'variant_type' => 'color',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['email' => $user->email]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'Black',
        ]);

        $response = $this->actingAs($user)->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertDontSee('You ordered sizes:');
    }

    public function test_checkout_success_hides_prompt_for_guest(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $order = Order::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'M',
        ]);

        $response = $this->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertDontSee('You ordered sizes:');
    }

    public function test_checkout_success_detects_sizes_from_option_values(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Red L',
            'variant_type' => 'custom',
            'option_values' => ['size' => 'L', 'color' => 'Red'],
            'price' => 29.99,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['email' => $user->email]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'Red L',
        ]);

        $response = $this->actingAs($user)->get(route('checkout.success', $order));

        $response->assertOk();
        $response->assertSee('You ordered sizes:');
        $response->assertSee('Save my sizes');
    }

    // ── Catalog Size Filter Tests ───────────────────────────────────────

    public function test_catalog_shows_shop_for_dropdown_when_user_has_profiles(): void
    {
        $user = User::factory()->create();

        SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
        ]);

        Product::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Shop for');
        $response->assertSee('My sizes');
    }

    public function test_catalog_hides_shop_for_dropdown_for_guest(): void
    {
        Product::factory()->create(['status' => 'active']);

        $response = $this->get(route('shop.index'));

        $response->assertOk();
        $response->assertDontSee('Shop for');
    }

    public function test_catalog_filters_products_by_size_profile(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
            'bottom_size' => null,
            'shoe_size' => null,
        ]);

        $matchingProduct = Product::factory()->create([
            'name' => 'Matching Shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Non Matching Shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingProduct->id,
            'name' => 'XXL',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('shop.index', ['size_profile' => $profile->id]));

        $response->assertOk();
        $response->assertSee('Matching Shirt');
        $response->assertDontSee('Non Matching Shirt');
    }

    public function test_catalog_filters_products_by_size_profile_option_values(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'L',
            'bottom_size' => null,
            'shoe_size' => null,
        ]);

        $matchingProduct = Product::factory()->create([
            'name' => 'Option Values Shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Red L',
            'variant_type' => 'custom',
            'option_values' => ['size' => 'L', 'color' => 'Red'],
            'price' => 29.99,
            'is_active' => true,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Wrong Size Shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingProduct->id,
            'name' => 'Red S',
            'variant_type' => 'custom',
            'option_values' => ['size' => 'S', 'color' => 'Red'],
            'price' => 29.99,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('shop.index', ['size_profile' => $profile->id]));

        $response->assertOk();
        $response->assertSee('Option Values Shirt');
        $response->assertDontSee('Wrong Size Shirt');
    }

    public function test_catalog_shows_all_products_without_size_profile_filter(): void
    {
        $user = User::factory()->create();

        SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
        ]);

        $product1 = Product::factory()->create([
            'name' => 'Shirt Alpha',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'name' => 'M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Shirt Beta',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'name' => 'XXL',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Shirt Alpha');
        $response->assertSee('Shirt Beta');
    }

    public function test_catalog_filters_products_by_size_in_variant_name(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'S',
            'bottom_size' => null,
            'shoe_size' => null,
        ]);

        $matchingProduct = Product::factory()->create([
            'name' => 'Soft Patch Hoodie',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Soft Patch Hoodie - Grey, S',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 49.99,
            'is_active' => true,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Other Hoodie',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingProduct->id,
            'name' => 'Other Hoodie - Grey, XXL',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 49.99,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('shop.index', ['size_profile' => $profile->id]));

        $response->assertOk();
        $response->assertSee('Soft Patch Hoodie');
        $response->assertDontSee('Other Hoodie');
    }
}

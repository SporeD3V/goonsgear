<?php

namespace Tests\Feature;

use App\Models\Category;
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

        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['status' => 'active']);
        $product->categories()->attach($category);

        $response = $this->actingAs($user)->get(route('shop.category', $category->slug));

        $response->assertOk();
        $response->assertSee('Shop for');
        $response->assertSee('My sizes');
    }

    public function test_catalog_hides_shop_for_dropdown_for_guest(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['status' => 'active']);
        $product->categories()->attach($category);

        $response = $this->get(route('shop.category', $category->slug));

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

        $topCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => 'top',
        ]);

        $matchingProduct = Product::factory()->create([
            'name' => 'Matching Shirt',
            'status' => 'active',
        ]);
        $matchingProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Non Matching Shirt',
            'status' => 'active',
        ]);
        $nonMatchingProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingProduct->id,
            'name' => 'XXL',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

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

        $topCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => 'top',
        ]);

        $matchingProduct = Product::factory()->create([
            'name' => 'Option Values Shirt',
            'status' => 'active',
        ]);
        $matchingProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Red L',
            'variant_type' => 'custom',
            'option_values' => ['size' => 'L', 'color' => 'Red'],
            'price' => 29.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Wrong Size Shirt',
            'status' => 'active',
        ]);
        $nonMatchingProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingProduct->id,
            'name' => 'Red S',
            'variant_type' => 'custom',
            'option_values' => ['size' => 'S', 'color' => 'Red'],
            'price' => 29.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

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

        $topCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => 'top',
        ]);

        $matchingProduct = Product::factory()->create([
            'name' => 'Soft Patch Hoodie',
            'status' => 'active',
        ]);
        $matchingProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $matchingProduct->id,
            'name' => 'Soft Patch Hoodie - Grey, S',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 49.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Other Hoodie',
            'status' => 'active',
        ]);
        $nonMatchingProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingProduct->id,
            'name' => 'Other Hoodie - Grey, XXL',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 49.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Soft Patch Hoodie');
        $response->assertDontSee('Other Hoodie');
    }

    public function test_catalog_size_filter_excludes_out_of_stock_variants(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
            'bottom_size' => null,
            'shoe_size' => null,
        ]);

        $topCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'shirts',
            'size_type' => 'top',
        ]);

        $outOfStockProduct = Product::factory()->create([
            'name' => 'Out Of Stock Size Shirt',
            'status' => 'active',
        ]);
        $outOfStockProduct->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $outOfStockProduct->id,
            'name' => 'Out Of Stock Size Shirt - M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
            'track_inventory' => true,
            'stock_quantity' => 0,
            'allow_backorder' => false,
            'is_preorder' => false,
        ]);

        // Out-of-stock products with matching size should be hidden
        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.category', $topCategory));

        $response->assertOk();
        $response->assertDontSee('Out Of Stock Size Shirt');
    }

    public function test_size_match_handles_size_before_color_in_variant_name(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => null,
            'bottom_size' => 'XXL',
            'shoe_size' => null,
        ]);

        $bottomCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'pants',
            'size_type' => 'bottom',
        ]);

        $product = Product::factory()->create([
            'name' => 'Mesh Shorts',
            'status' => 'active',
        ]);
        $product->categories()->attach($bottomCategory);

        // Variant with size before color: "Product - XXL, White"
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Mesh Shorts - XXL, White',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 39.99,
            'is_active' => true,
            'track_inventory' => true,
            'stock_quantity' => 2,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.category', $bottomCategory));

        $response->assertOk();
        $response->assertSee('Mesh Shorts');
    }

    public function test_category_quick_filter_buttons_are_rendered(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'L',
        ]);

        $category = Category::factory()->create([
            'is_active' => true,
            'slug' => 'test-category',
            'name' => 'Test Category',
        ]);

        $product = Product::factory()->create(['status' => 'active']);
        $product->categories()->attach($category);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee(route('shop.category', $category), false);
    }

    public function test_size_filter_shows_non_sized_products(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
        ]);

        // Vinyl category without size_type
        $vinylCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => null,
        ]);

        $vinyl = Product::factory()->create([
            'name' => 'Classic Hip Hop Vinyl',
            'status' => 'active',
        ]);
        $vinyl->categories()->attach($vinylCategory);

        ProductVariant::factory()->create([
            'product_id' => $vinyl->id,
            'name' => 'Default',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 19.99,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        // Shirt in a top category
        $topCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => 'top',
        ]);

        $shirt = Product::factory()->create([
            'name' => 'Sized Shirt Product',
            'status' => 'active',
        ]);
        $shirt->categories()->attach($topCategory);

        ProductVariant::factory()->create([
            'product_id' => $shirt->id,
            'name' => 'Sized Shirt Product - M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 29.99,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Classic Hip Hop Vinyl');
        $response->assertSee('Sized Shirt Product');
    }

    public function test_size_filter_narrows_to_top_size_for_top_category(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'S',
            'bottom_size' => 'M',
        ]);

        $tshirtCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'tshirts',
            'name' => 'T-Shirts',
            'size_type' => 'top',
        ]);

        $smallShirt = Product::factory()->create([
            'name' => 'Small Shirt',
            'status' => 'active',
        ]);
        $smallShirt->categories()->attach($tshirtCategory);

        ProductVariant::factory()->create([
            'product_id' => $smallShirt->id,
            'name' => 'Small Shirt - S',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 25.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $mediumShirt = Product::factory()->create([
            'name' => 'Medium Only Shirt',
            'status' => 'active',
        ]);
        $mediumShirt->categories()->attach($tshirtCategory);

        ProductVariant::factory()->create([
            'product_id' => $mediumShirt->id,
            'name' => 'Medium Only Shirt - M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 25.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.category', $tshirtCategory));

        $response->assertOk();
        $response->assertSee('Small Shirt');
        $response->assertDontSee('Medium Only Shirt');
    }

    public function test_size_filter_uses_all_sizes_for_category_without_size_type(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'S',
            'bottom_size' => 'M',
        ]);

        $saleCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'sale',
            'name' => 'SALE',
            'size_type' => null,
        ]);

        $smallProduct = Product::factory()->create([
            'name' => 'Sale Item Small',
            'status' => 'active',
        ]);
        $smallProduct->categories()->attach($saleCategory);

        ProductVariant::factory()->create([
            'product_id' => $smallProduct->id,
            'name' => 'Sale Item Small - S',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 15.00,
            'compare_at_price' => 25.00,
            'is_active' => true,
            'stock_quantity' => 3,
        ]);

        $mediumProduct = Product::factory()->create([
            'name' => 'Sale Item Medium',
            'status' => 'active',
        ]);
        $mediumProduct->categories()->attach($saleCategory);

        ProductVariant::factory()->create([
            'product_id' => $mediumProduct->id,
            'name' => 'Sale Item Medium - M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 15.00,
            'compare_at_price' => 25.00,
            'is_active' => true,
            'stock_quantity' => 3,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.category', $saleCategory));

        $response->assertOk();
        // Both show because SALE has no size_type — products only in unsized categories pass through
        $response->assertSee('Sale Item Small');
        $response->assertSee('Sale Item Medium');
    }

    public function test_multi_category_product_uses_sized_category_for_filtering(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'S',
            'bottom_size' => 'M',
        ]);

        $topCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'tshirts',
            'size_type' => 'top',
        ]);

        // Artist category has no size_type
        $artistCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'sean-p',
            'size_type' => null,
        ]);

        // Sean P! shirt in BOTH categories — should filter by top_size=S
        $matchingShirt = Product::factory()->create([
            'name' => 'Sean P Matching Shirt',
            'status' => 'active',
        ]);
        $matchingShirt->categories()->attach([$topCategory->id, $artistCategory->id]);

        ProductVariant::factory()->create([
            'product_id' => $matchingShirt->id,
            'name' => 'Sean P Matching Shirt - S',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 30.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $nonMatchingShirt = Product::factory()->create([
            'name' => 'Sean P Wrong Size Shirt',
            'status' => 'active',
        ]);
        $nonMatchingShirt->categories()->attach([$topCategory->id, $artistCategory->id]);

        ProductVariant::factory()->create([
            'product_id' => $nonMatchingShirt->id,
            'name' => 'Sean P Wrong Size Shirt - XL',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 30.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        // Browsing via artist category (no size_type) — still filters correctly
        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.category', $artistCategory));

        $response->assertOk();
        $response->assertSee('Sean P Matching Shirt');
        $response->assertDontSee('Sean P Wrong Size Shirt');
    }

    public function test_shoe_size_maps_to_biggie_smalls_sock_variants(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
            'shoe_size' => '44',
        ]);

        $shoeCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'socks',
            'size_type' => 'shoe',
        ]);

        // Socks with "Biggie" variant (43-46 EU) — should match shoe_size=44
        $biggieSocks = Product::factory()->create([
            'name' => 'Hip Hop Biggie Socks',
            'status' => 'active',
        ]);
        $biggieSocks->categories()->attach($shoeCategory);

        ProductVariant::factory()->create([
            'product_id' => $biggieSocks->id,
            'name' => 'Hip Hop Biggie Socks - Biggie',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 12.99,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        // Socks with only "Smalls" variant (36-42 EU) — should NOT match shoe_size=44
        $smallsOnlySocks = Product::factory()->create([
            'name' => 'Smalls Only Socks',
            'status' => 'active',
        ]);
        $smallsOnlySocks->categories()->attach($shoeCategory);

        ProductVariant::factory()->create([
            'product_id' => $smallsOnlySocks->id,
            'name' => 'Smalls Only Socks - Smalls',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 12.99,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Hip Hop Biggie Socks');
        $response->assertDontSee('Smalls Only Socks');
    }

    public function test_shoe_size_smalls_range_matches_correctly(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'shoe_size' => '39',
        ]);

        $shoeCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => 'shoe',
        ]);

        $smallsSocks = Product::factory()->create([
            'name' => 'Smalls Range Socks',
            'status' => 'active',
        ]);
        $smallsSocks->categories()->attach($shoeCategory);

        ProductVariant::factory()->create([
            'product_id' => $smallsSocks->id,
            'name' => 'Smalls Range Socks - Smalls',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 12.99,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        $biggieSocks = Product::factory()->create([
            'name' => 'Biggie Range Socks',
            'status' => 'active',
        ]);
        $biggieSocks->categories()->attach($shoeCategory);

        ProductVariant::factory()->create([
            'product_id' => $biggieSocks->id,
            'name' => 'Biggie Range Socks - Biggie',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 12.99,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('Smalls Range Socks');
        $response->assertDontSee('Biggie Range Socks');
    }

    public function test_products_in_unset_dimension_pass_through(): void
    {
        $user = User::factory()->create();

        // User only has top_size set, no bottom_size
        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
            'bottom_size' => null,
            'shoe_size' => null,
        ]);

        $bottomCategory = Category::factory()->create([
            'is_active' => true,
            'slug' => 'pants',
            'size_type' => 'bottom',
        ]);

        $pants = Product::factory()->create([
            'name' => 'Passthrough Pants',
            'status' => 'active',
        ]);
        $pants->categories()->attach($bottomCategory);

        ProductVariant::factory()->create([
            'product_id' => $pants->id,
            'name' => 'Passthrough Pants - XXL',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 40.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        // Pants should still show even though user has no bottom_size set
        $response->assertSee('Passthrough Pants');
    }

    public function test_preselect_sizes_include_biggie_smalls_mapping(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
            'shoe_size' => '44',
        ]);

        $shoeCategory = Category::factory()->create([
            'is_active' => true,
            'size_type' => 'shoe',
        ]);

        $socks = Product::factory()->create([
            'name' => 'Preselect Biggie Socks',
            'status' => 'active',
        ]);
        $socks->categories()->attach($shoeCategory);

        ProductVariant::factory()->create([
            'product_id' => $socks->id,
            'name' => 'Preselect Biggie Socks - Biggie',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 12.99,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        // The preselect data should include "Biggie" (mapped from shoe_size=44)
        $response->assertSee('"Biggie"', false);
    }

    public function test_catalog_cards_include_preselect_sizes_when_profile_active(): void
    {
        $user = User::factory()->create();

        $profile = SizeProfile::factory()->self()->create([
            'user_id' => $user->id,
            'top_size' => 'M',
            'bottom_size' => 'L',
        ]);

        $product = Product::factory()->create([
            'name' => 'Preselect Test Shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Preselect Test Shirt - Red, M',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 35.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Preselect Test Shirt - Red, L',
            'variant_type' => 'custom',
            'option_values' => null,
            'price' => 35.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_filters' => ['size_profile' => $profile->id]])
            ->get(route('shop.index'));

        $response->assertOk();
        $response->assertSee('data-catalog-preselect-sizes', false);
        $response->assertSee('"M"', false);
    }

    public function test_catalog_cards_omit_preselect_sizes_when_no_profile(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'name' => 'No Profile Test Shirt',
            'status' => 'active',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'No Profile Test Shirt - M',
            'variant_type' => 'size',
            'option_values' => null,
            'price' => 25.00,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('shop.index'));

        $response->assertOk();
        $response->assertDontSee('data-catalog-preselect-sizes', false);
    }
}

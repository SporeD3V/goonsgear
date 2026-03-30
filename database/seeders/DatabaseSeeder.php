<?php

namespace Database\Seeders;

use App\Models\BundleDiscount;
use App\Models\BundleDiscountItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\RegionalDiscount;
use App\Models\Tag;
use App\Models\TagFollow;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ── Admin user ──────────────────────────────────────────
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@goonsgear.com',
        ]);

        // ── Regular users ───────────────────────────────────────
        $users = User::factory(10)->create();

        // ── Categories ──────────────────────────────────────────
        $categories = collect([
            'T-Shirts', 'Hoodies', 'Caps', 'Accessories', 'Stickers',
        ])->map(fn (string $name, int $i) => Category::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'sort_order' => $i,
        ]));

        // Sub-categories under T-Shirts
        $tees = $categories->first();
        Category::factory()->create(['name' => 'Graphic Tees', 'slug' => 'graphic-tees', 'parent_id' => $tees->id]);
        Category::factory()->create(['name' => 'Plain Tees', 'slug' => 'plain-tees', 'parent_id' => $tees->id]);

        // ── Tags ────────────────────────────────────────────────
        $tags = collect([
            ['name' => 'Street Art', 'type' => 'artist'],
            ['name' => 'Retro Wave', 'type' => 'brand'],
            ['name' => 'Neon Nights', 'type' => 'artist'],
            ['name' => 'GoonsGear OG', 'type' => 'brand'],
        ])->map(fn (array $t) => Tag::factory()->create([
            'name' => $t['name'],
            'slug' => str($t['name'])->slug()->toString(),
            'type' => $t['type'],
        ]));

        // Some users follow tags
        $users->take(5)->each(function (User $user) use ($tags) {
            TagFollow::factory()->create([
                'user_id' => $user->id,
                'tag_id' => $tags->random()->id,
            ]);
        });

        // ── Products with variants and media ────────────────────
        $sizes = ['S', 'M', 'L', 'XL'];

        $categories->each(function (Category $category) use ($sizes, $tags) {
            $productCount = $category->name === 'Stickers' ? 3 : 4;

            Product::factory($productCount)
                ->create([
                    'primary_category_id' => $category->id,
                    'is_featured' => fake()->boolean(30),
                ])
                ->each(function (Product $product) use ($category, $sizes, $tags) {
                    // Attach to primary category
                    $product->categories()->attach($category->id, ['position' => 0]);

                    // Attach 1-2 random tags
                    $product->tags()->attach($tags->random(rand(1, 2))->pluck('id'));

                    // Create variants
                    if (in_array($category->name, ['T-Shirts', 'Hoodies'])) {
                        foreach ($sizes as $i => $size) {
                            ProductVariant::factory()->create([
                                'product_id' => $product->id,
                                'name' => $size,
                                'sku' => strtoupper(str($product->slug)->slug('-').'-'.$size),
                                'option_values' => ['size' => $size],
                                'price' => $category->name === 'Hoodies' ? fake()->randomFloat(2, 45, 89) : fake()->randomFloat(2, 19, 39),
                                'stock_quantity' => fake()->numberBetween(5, 40),
                                'position' => $i,
                            ]);
                        }
                    } else {
                        ProductVariant::factory()->create([
                            'product_id' => $product->id,
                            'name' => 'Default',
                            'sku' => strtoupper(str($product->slug)->slug('-').'-DEF'),
                            'option_values' => [],
                            'price' => fake()->randomFloat(2, 5, 29),
                            'stock_quantity' => fake()->numberBetween(10, 100),
                        ]);
                    }

                    // Primary media for each product
                    ProductMedia::factory()->create([
                        'product_id' => $product->id,
                        'is_primary' => true,
                        'position' => 0,
                    ]);
                });
        });

        // ── Coupons ─────────────────────────────────────────────
        Coupon::factory()->create([
            'code' => 'WELCOME10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'description' => '10% off for new customers',
            'is_stackable' => false,
        ]);
        Coupon::factory()->create([
            'code' => 'FLAT5',
            'type' => Coupon::TYPE_FIXED,
            'value' => 5,
            'description' => '€5 off any order',
            'minimum_subtotal' => 25,
        ]);
        Coupon::factory(3)->create();

        // ── Regional discounts ──────────────────────────────────
        RegionalDiscount::factory()->create([
            'country_code' => 'RO',
            'discount_type' => RegionalDiscount::TYPE_PERCENT,
            'discount_value' => 15,
            'reason' => 'Eastern Europe pricing',
        ]);
        RegionalDiscount::factory()->create([
            'country_code' => 'BG',
            'discount_type' => RegionalDiscount::TYPE_PERCENT,
            'discount_value' => 15,
            'reason' => 'Eastern Europe pricing',
        ]);

        // ── Bundle discount ─────────────────────────────────────
        $firstTwoVariants = ProductVariant::query()->limit(2)->get();
        if ($firstTwoVariants->count() >= 2) {
            $bundle = BundleDiscount::factory()->create([
                'name' => 'Tee + Cap Combo',
                'discount_type' => BundleDiscount::TYPE_PERCENT,
                'discount_value' => 12,
            ]);
            $firstTwoVariants->each(fn (ProductVariant $v, int $i) => BundleDiscountItem::factory()->create([
                'bundle_discount_id' => $bundle->id,
                'product_variant_id' => $v->id,
                'min_quantity' => 1,
                'position' => $i,
            ]));
        }

        // ── Orders ──────────────────────────────────────────────
        $allVariants = ProductVariant::with('product')->get();

        collect(range(1, 8))->each(function (int $i) use ($users, $allVariants) {
            $variant = $allVariants->random();
            $qty = fake()->numberBetween(1, 3);
            $unitPrice = (float) $variant->price;
            $lineTotal = round($unitPrice * $qty, 2);

            $status = fake()->randomElement(['pending', 'processing', 'shipped', 'delivered']);

            $order = Order::factory()->create([
                'email' => $users->random()->email,
                'status' => $status,
                'payment_status' => $status === 'pending' ? 'pending' : 'paid',
                'subtotal' => $lineTotal,
                'total' => $lineTotal,
                'shipped_at' => in_array($status, ['shipped', 'delivered']) ? now()->subDays(rand(1, 5)) : null,
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'line_total' => $lineTotal,
            ]);
        });
    }
}

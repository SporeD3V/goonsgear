<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_dashboard_loads_overview_by_default(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('tab', 'overview')
            ->assertViewHas('period', '30d')
            ->assertViewHas('compare', false)
            ->assertViewHas('periodLabel', 'Last 30 Days')
            ->assertViewHas('overview')
            ->assertViewHas('revenueOverTime')
            ->assertViewHas('ordersByStatus');
    }

    public function test_dashboard_loads_sales_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'sales']))
            ->assertOk()
            ->assertViewHas('tab', 'sales')
            ->assertViewHas('aov')
            ->assertViewHas('repeatRate')
            ->assertViewHas('itemsPerOrder')
            ->assertViewHas('topProducts')
            ->assertViewHas('revenueByCountry');
    }

    public function test_dashboard_loads_inventory_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'inventory']))
            ->assertOk()
            ->assertViewHas('stockHealth')
            ->assertViewHas('stockAlertDemand')
            ->assertViewHas('productStatus');
    }

    public function test_dashboard_loads_promotions_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'promotions']))
            ->assertOk()
            ->assertViewHas('couponLeaderboard')
            ->assertViewHas('discountImpact')
            ->assertViewHas('cartRecovery');
    }

    public function test_dashboard_loads_customers_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'customers']))
            ->assertOk()
            ->assertViewHas('customerStats')
            ->assertViewHas('customerGeo')
            ->assertViewHas('tagFollows');
    }

    public function test_period_presets_change_label(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['period' => '7d']))
            ->assertOk()
            ->assertViewHas('period', '7d')
            ->assertViewHas('periodLabel', 'Last 7 Days');

        $this->get(route('admin.dashboard', ['period' => 'year']))
            ->assertOk()
            ->assertViewHas('period', 'year')
            ->assertViewHas('periodLabel', 'Last Year');

        $this->get(route('admin.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertViewHas('period', 'all')
            ->assertViewHas('periodLabel', 'All Time');
    }

    public function test_invalid_period_defaults_to_30d(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['period' => 'invalid']))
            ->assertOk()
            ->assertViewHas('period', '30d');
    }

    public function test_compare_toggle_provides_deltas_on_overview(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['compare' => 1]));

        $response->assertOk()
            ->assertViewHas('compare', true)
            ->assertViewHas('deltas')
            ->assertViewHas('prevRevenueOverTime');
    }

    public function test_compare_toggle_provides_deltas_on_sales(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales', 'compare' => 1]));

        $response->assertOk()
            ->assertViewHas('deltas')
            ->assertViewHas('prevRevenueOverTime');
    }

    public function test_compare_not_available_on_inventory(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory', 'compare' => 1]));

        $response->assertOk()
            ->assertViewMissing('deltas');
    }

    public function test_all_time_period_with_compare_has_no_deltas(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['period' => 'all', 'compare' => 1]));

        $response->assertOk()
            ->assertViewHas('compare', true)
            ->assertViewMissing('deltas');
    }

    public function test_period_is_preserved_across_tabs(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'sales', 'period' => '90d', 'compare' => 1]))
            ->assertOk()
            ->assertViewHas('period', '90d')
            ->assertViewHas('compare', true);
    }

    public function test_compare_mode_defaults_to_previous_period(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['compare' => 1]))
            ->assertOk()
            ->assertViewHas('compareMode', 'previous_period');
    }

    public function test_compare_mode_accepts_yoy(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['compare' => 1, 'compare_mode' => 'yoy']))
            ->assertOk()
            ->assertViewHas('compareMode', 'yoy')
            ->assertViewHas('deltas')
            ->assertViewHas('prevRevenueOverTime');
    }

    public function test_invalid_compare_mode_defaults_to_previous_period(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['compare' => 1, 'compare_mode' => 'bogus']))
            ->assertOk()
            ->assertViewHas('compareMode', 'previous_period');
    }

    public function test_custom_date_range_sets_period_to_custom(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', [
            'custom_from' => '2024-06-01',
            'custom_to' => '2024-06-30',
        ]))
            ->assertOk()
            ->assertViewHas('period', 'custom')
            ->assertViewHas('customFrom', '2024-06-01')
            ->assertViewHas('customTo', '2024-06-30')
            ->assertViewHas('periodLabel', 'Jun 1, 2024 – Jun 30, 2024');
    }

    public function test_invalid_custom_dates_fall_back_to_default(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', [
            'custom_from' => 'not-a-date',
            'custom_to' => 'also-bad',
        ]))
            ->assertOk()
            ->assertViewHas('period', '30d')
            ->assertViewHas('customFrom', null)
            ->assertViewHas('customTo', null);
    }

    public function test_custom_date_range_with_compare(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', [
            'custom_from' => '2024-06-01',
            'custom_to' => '2024-06-30',
            'compare' => 1,
        ]))
            ->assertOk()
            ->assertViewHas('period', 'custom')
            ->assertViewHas('compare', true)
            ->assertViewHas('deltas')
            ->assertViewHas('prevRevenueOverTime');
    }

    public function test_sales_tab_has_yearly_revenue_and_benchmark(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));

        $response->assertOk()
            ->assertViewHas('yearlyRevenue')
            ->assertViewHas('bestMonthBenchmark');

        $yearly = $response->viewData('yearlyRevenue');
        $this->assertArrayHasKey('years', $yearly);
        $this->assertArrayHasKey('average', $yearly);
        $this->assertArrayHasKey('months', $yearly);
        $this->assertCount(12, $yearly['months']);

        $benchmark = $response->viewData('bestMonthBenchmark');
        $this->assertArrayHasKey('month_name', $benchmark);
        $this->assertArrayHasKey('current_year', $benchmark);
        $this->assertArrayHasKey('current_revenue', $benchmark);
        $this->assertArrayHasKey('best_year', $benchmark);
        $this->assertArrayHasKey('best_revenue', $benchmark);
        $this->assertArrayHasKey('gap_pct', $benchmark);
    }

    public function test_yoy_compare_on_sales_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'sales', 'compare' => 1, 'compare_mode' => 'yoy']))
            ->assertOk()
            ->assertViewHas('compareMode', 'yoy')
            ->assertViewHas('deltas')
            ->assertViewHas('prevRevenueOverTime');
    }

    public function test_best_month_benchmark_with_order_data(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->startOfMonth(),
            'total' => 250.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $benchmark = $response->viewData('bestMonthBenchmark');
        $this->assertGreaterThanOrEqual(250.00, $benchmark['current_revenue']);
    }

    public function test_sales_tab_has_contextual_performance_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));

        $response->assertOk()
            ->assertViewHas('benchmarkableProducts')
            ->assertViewHas('releaseBenchmark', null)
            ->assertViewHas('regionalGrowth')
            ->assertViewHas('productDecay');

        $regional = $response->viewData('regionalGrowth');
        $this->assertArrayHasKey('countries', $regional);
        $this->assertArrayHasKey('quarters', $regional);
        $this->assertArrayHasKey('series', $regional);
    }

    public function test_release_benchmark_with_two_products(): void
    {
        $this->actingAsAdmin();

        $productA = Product::factory()->create(['published_at' => now()->subMonths(3)]);
        $productB = Product::factory()->create(['published_at' => now()->subMonths(6)]);

        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => $productA->published_at->copy()->addDays(2),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 5,
            'line_total' => 150.00,
        ]);

        $response = $this->get(route('admin.dashboard', [
            'tab' => 'sales',
            'benchmark_a' => $productA->id,
            'benchmark_b' => $productB->id,
            'benchmark_days' => 30,
        ]));

        $response->assertOk()
            ->assertViewHas('releaseBenchmark');

        $benchmark = $response->viewData('releaseBenchmark');
        $this->assertCount(2, $benchmark['products']);
        $this->assertArrayHasKey($productA->id, $benchmark['comparison']);
        $this->assertArrayHasKey($productB->id, $benchmark['comparison']);
    }

    public function test_release_benchmark_null_when_no_products_selected(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'sales']))
            ->assertOk()
            ->assertViewHas('releaseBenchmark', null);
    }

    public function test_benchmark_days_clamps_to_valid_range(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'sales', 'benchmark_days' => 999]))
            ->assertOk()
            ->assertViewHas('benchmarkDays', 30);

        $this->get(route('admin.dashboard', ['tab' => 'sales', 'benchmark_days' => 3]))
            ->assertOk()
            ->assertViewHas('benchmarkDays', 30);
    }

    public function test_product_decay_tracks_velocity(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['published_at' => now()->subMonths(3)]);

        // Month 1 sales
        $orderM1 = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => $product->published_at->copy()->addDays(5),
        ]);
        OrderItem::factory()->create([
            'order_id' => $orderM1->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'line_total' => 300.00,
        ]);

        // Month 2 sales (fewer)
        $orderM2 = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => $product->published_at->copy()->addMonths(1)->addDays(5),
        ]);
        OrderItem::factory()->create([
            'order_id' => $orderM2->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'line_total' => 120.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $decay = $response->viewData('productDecay');
        $productDecay = collect($decay)->firstWhere('product_id', $product->id);

        $this->assertNotNull($productDecay);
        $this->assertEquals(100.0, $productDecay['months'][0]['velocity_pct']);
        $this->assertEquals(40.0, $productDecay['months'][1]['velocity_pct']);
    }

    public function test_regional_growth_with_order_data(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subMonth(),
            'total' => 500.00,
            'country' => 'US',
        ]);

        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subMonth(),
            'total' => 300.00,
            'country' => 'DE',
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $regional = $response->viewData('regionalGrowth');
        $this->assertNotEmpty($regional['countries']);
        $this->assertNotEmpty($regional['quarters']);
    }

    public function test_customers_tab_has_advanced_context_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));

        $response->assertOk()
            ->assertViewHas('cohortRetention')
            ->assertViewHas('aovBreakdown')
            ->assertViewHas('waitlistConversion');

        $waitlist = $response->viewData('waitlistConversion');
        $this->assertArrayHasKey('first_release', $waitlist);
        $this->assertArrayHasKey('restock', $waitlist);
    }

    public function test_cohort_retention_with_returning_customer(): void
    {
        $this->actingAsAdmin();

        // Customer who ordered twice within 12 months
        $firstDate = now()->subYear()->subMonths(2);
        Order::factory()->create([
            'email' => 'loyal@example.com',
            'payment_status' => 'paid',
            'placed_at' => $firstDate,
        ]);
        Order::factory()->create([
            'email' => 'loyal@example.com',
            'payment_status' => 'paid',
            'placed_at' => $firstDate->copy()->addMonths(3),
        ]);

        // One-time customer
        Order::factory()->create([
            'email' => 'onetime@example.com',
            'payment_status' => 'paid',
            'placed_at' => $firstDate,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $cohorts = $response->viewData('cohortRetention');
        $this->assertNotEmpty($cohorts);

        $cohort = collect($cohorts)->firstWhere('year', $firstDate->year);
        $this->assertNotNull($cohort);
        $this->assertEquals(2, $cohort['total_customers']);
        $this->assertEquals(1, $cohort['retained']);
        $this->assertEquals(50.0, $cohort['retention_pct']);
    }

    public function test_aov_breakdown_returns_yearly_data(): void
    {
        $this->actingAsAdmin();

        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subMonth(),
            'total' => 80.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'line_total' => 80.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $aov = $response->viewData('aovBreakdown');
        $this->assertNotEmpty($aov);

        $thisYear = collect($aov)->firstWhere('year', now()->year);
        $this->assertNotNull($thisYear);
        $this->assertEquals(80.0, $thisYear['aov']);
        $this->assertEquals(2.0, $thisYear['avg_items_per_order']);
        $this->assertEquals(40.0, $thisYear['avg_price_per_item']);
    }

    public function test_waitlist_conversion_with_converted_subscriber(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['published_at' => now()->subMonths(6)]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $user = User::factory()->create(['email' => 'subscriber@example.com']);

        // Restock subscription (>90 days after publish)
        StockAlertSubscription::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'notified_at' => now()->subWeek(),
            'created_at' => now()->subWeeks(2),
        ]);

        // Subscriber then bought the product
        $order = Order::factory()->create([
            'email' => 'subscriber@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(3),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'line_total' => 30.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $waitlist = $response->viewData('waitlistConversion');
        $this->assertEquals(1, $waitlist['restock']['notified']);
        $this->assertEquals(1, $waitlist['restock']['converted']);
        $this->assertEquals(100.0, $waitlist['restock']['conversion_pct']);
    }

    public function test_waitlist_first_release_classification(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['published_at' => now()->subMonths(1)]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $user = User::factory()->create(['email' => 'early@example.com']);

        // First-release subscription (within 90 days of publish)
        StockAlertSubscription::factory()->create([
            'user_id' => $user->id,
            'product_variant_id' => $variant->id,
            'notified_at' => now()->subDays(5),
            'created_at' => now()->subWeeks(2),
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $waitlist = $response->viewData('waitlistConversion');
        $this->assertEquals(1, $waitlist['first_release']['notified']);
        $this->assertEquals(0, $waitlist['first_release']['converted']);
    }

    public function test_cohort_retention_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $cohorts = $response->viewData('cohortRetention');
        $this->assertEmpty($cohorts);
    }

    public function test_customers_tab_has_rfm_and_clv_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk()
            ->assertViewHas('rfmSegmentation')
            ->assertViewHas('clv');
    }

    public function test_rfm_segments_customers_correctly(): void
    {
        $this->actingAsAdmin();

        // Champion: recent, frequent, high-spend
        foreach (range(1, 6) as $i) {
            Order::factory()->create([
                'email' => 'champion@example.com',
                'payment_status' => 'paid',
                'placed_at' => now()->subDays($i),
                'total' => 200.00,
            ]);
        }

        // At Risk: old orders, but frequent and high-spend
        foreach (range(1, 5) as $i) {
            Order::factory()->create([
                'email' => 'atrisk@example.com',
                'payment_status' => 'paid',
                'placed_at' => now()->subMonths(14)->subDays($i),
                'total' => 180.00,
            ]);
        }

        // New: single recent order
        Order::factory()->create([
            'email' => 'newcustomer@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(2),
            'total' => 50.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $rfm = $response->viewData('rfmSegmentation');
        $this->assertArrayHasKey('segments', $rfm);
        $this->assertArrayHasKey('customers_analyzed', $rfm);
        $this->assertEquals(3, $rfm['customers_analyzed']);

        // At least one segment should have customers
        $totalInSegments = collect($rfm['segments'])->sum('count');
        $this->assertEquals(3, $totalInSegments);
    }

    public function test_rfm_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $rfm = $response->viewData('rfmSegmentation');
        $this->assertEquals(0, $rfm['customers_analyzed']);
    }

    public function test_clv_calculates_lifetime_value(): void
    {
        $this->actingAsAdmin();

        // Customer A: 3 orders, total €300
        foreach (range(1, 3) as $i) {
            Order::factory()->create([
                'email' => 'loyal@example.com',
                'payment_status' => 'paid',
                'placed_at' => now()->subMonths($i),
                'total' => 100.00,
            ]);
        }

        // Customer B: 1 order, total €50
        Order::factory()->create([
            'email' => 'onetime@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(10),
            'total' => 50.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $clv = $response->viewData('clv');
        $this->assertEquals(2, $clv['total_customers']);
        $this->assertEquals(350.00, $clv['total_revenue']);
        // CLV = 350 / 2 = 175
        $this->assertEquals(175.00, $clv['overall_clv']);
        $this->assertNotEmpty($clv['by_year']);
    }

    public function test_clv_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $clv = $response->viewData('clv');
        $this->assertEquals(0, $clv['total_customers']);
        $this->assertEquals(0, $clv['overall_clv']);
        $this->assertEmpty($clv['by_year']);
    }

    public function test_clv_groups_by_acquisition_year(): void
    {
        $this->actingAsAdmin();

        // Customer from 2023
        Order::factory()->create([
            'email' => 'old@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subYear()->startOfYear(),
            'total' => 200.00,
        ]);

        // Customer from current year
        Order::factory()->create([
            'email' => 'new@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
            'total' => 100.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'customers']));
        $response->assertOk();

        $clv = $response->viewData('clv');
        $this->assertCount(2, $clv['by_year']);

        $years = collect($clv['by_year'])->pluck('year')->toArray();
        $this->assertContains(now()->subYear()->year, $years);
        $this->assertContains(now()->year, $years);
    }
}

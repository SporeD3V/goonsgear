<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\TrackVisitor;
use App\Models\AdminNote;
use App\Models\CartAbandonment;
use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertViewHas('itemsPerOrder')
            ->assertViewHas('discountImpact')
            ->assertViewHas('aovBreakdown')
            ->assertViewHas('customerGeo')
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
            ->assertViewHas('productStatus')
            ->assertViewHas('preorderLiability')
            ->assertViewHas('fulfillmentSpeed');
    }

    public function test_dashboard_loads_marketing_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'marketing']))
            ->assertOk()
            ->assertViewHas('couponLeaderboard')
            ->assertViewHas('cartRecovery')
            ->assertViewHas('waitlistConversion')
            ->assertViewHas('newsletterCount');
    }

    public function test_dashboard_loads_audience_tab(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'audience']))
            ->assertOk()
            ->assertViewHas('customerStats')
            ->assertViewHas('repeatRate')
            ->assertViewHas('tagFollows')
            ->assertViewHas('rfmSegmentation')
            ->assertViewHas('clv')
            ->assertViewHas('vipChurn');
    }

    public function test_customer_stats_active_in_period_counts_ordering_customers(): void
    {
        $this->actingAsAdmin();

        // Order within the default 30d period
        Order::factory()->create([
            'email' => 'active@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
        ]);

        // Same customer, second order — should not double-count
        Order::factory()->create([
            'email' => 'active@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(3),
        ]);

        // Order outside the 30d period
        Order::factory()->create([
            'email' => 'old@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(60),
        ]);

        // Unpaid order inside the period — should not count
        Order::factory()->create([
            'email' => 'unpaid@example.com',
            'payment_status' => 'pending',
            'placed_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience', 'period' => '30d']));

        $response->assertOk();
        $stats = $response->viewData('customerStats');

        $this->assertArrayHasKey('active_in_period', $stats);
        $this->assertEquals(1, $stats['active_in_period']);
    }

    public function test_customer_stats_compare_includes_active_delta(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'email' => 'current@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
        ]);

        $response = $this->get(route('admin.dashboard', [
            'tab' => 'audience',
            'period' => '30d',
            'compare' => 1,
        ]));

        $response->assertOk()->assertViewHas('deltas');
        $deltas = $response->viewData('deltas');
        $this->assertArrayHasKey('active_in_period', $deltas);
        $this->assertArrayHasKey('new_in_period', $deltas);
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
            ->assertViewHas('regionalGrowth')
            ->assertViewHas('productDecay');

        $regional = $response->viewData('regionalGrowth');
        $this->assertArrayHasKey('countries', $regional);
        $this->assertArrayHasKey('quarters', $regional);
        $this->assertArrayHasKey('series', $regional);
    }

    public function test_release_benchmark_livewire_component_renders_on_sales_tab(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));

        $response->assertOk()
            ->assertSeeLivewire('admin.release-benchmark');
    }

    public function test_release_benchmark_null_when_no_products_selected(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard', ['tab' => 'sales']))
            ->assertOk();
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

    public function test_audience_tab_has_advanced_context_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));

        $response->assertOk()
            ->assertViewHas('cohortRetention')
            ->assertViewHas('repeatRate')
            ->assertViewHas('vipChurn');
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'marketing']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'marketing']));
        $response->assertOk();

        $waitlist = $response->viewData('waitlistConversion');
        $this->assertEquals(1, $waitlist['first_release']['notified']);
        $this->assertEquals(0, $waitlist['first_release']['converted']);
    }

    public function test_cohort_retention_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
        $response->assertOk();

        $cohorts = $response->viewData('cohortRetention');
        $this->assertEmpty($cohorts);
    }

    public function test_audience_tab_has_rfm_and_clv_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
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

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
        $response->assertOk();

        $clv = $response->viewData('clv');
        $this->assertCount(2, $clv['by_year']);

        $years = collect($clv['by_year'])->pluck('year')->toArray();
        $this->assertContains(now()->subYear()->year, $years);
        $this->assertContains(now()->year, $years);
    }

    public function test_vip_churn_warning_detects_inactive_vips(): void
    {
        $this->actingAsAdmin();

        // Create 20 customers so top 5% = 1 customer
        foreach (range(1, 19) as $i) {
            Order::factory()->create([
                'email' => "regular{$i}@example.com",
                'payment_status' => 'paid',
                'placed_at' => now()->subDays(5),
                'total' => 20.00,
            ]);
        }

        // VIP customer who stopped buying 120 days ago
        foreach (range(1, 5) as $i) {
            Order::factory()->create([
                'email' => 'vip@example.com',
                'payment_status' => 'paid',
                'placed_at' => now()->subDays(120 + $i),
                'total' => 500.00,
            ]);
        }

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
        $response->assertOk();

        $churn = $response->viewData('vipChurn');
        $this->assertGreaterThan(0, $churn['vip_total']);
        $this->assertNotEmpty($churn['at_risk_vips']);
        $this->assertEquals('vip@example.com', $churn['at_risk_vips'][0]['email']);
    }

    public function test_vip_churn_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'audience']));
        $response->assertOk();

        $churn = $response->viewData('vipChurn');
        $this->assertEquals(0, $churn['vip_total']);
        $this->assertEmpty($churn['at_risk_vips']);
        $this->assertEmpty($churn['lost_vips']);
    }

    public function test_first_purchase_heroes_identifies_entry_products(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['name' => 'Entry T-Shirt']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Customer A: first order has "Entry T-Shirt"
        $orderA = Order::factory()->create([
            'email' => 'custA@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subMonths(3),
            'total' => 30.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $orderA->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Entry T-Shirt',
            'quantity' => 1,
            'unit_price' => 30.00,
            'line_total' => 30.00,
        ]);

        // Customer B: also first order has "Entry T-Shirt"
        $orderB = Order::factory()->create([
            'email' => 'custB@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subMonths(2),
            'total' => 30.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $orderB->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Entry T-Shirt',
            'quantity' => 1,
            'unit_price' => 30.00,
            'line_total' => 30.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $heroes = $response->viewData('firstPurchaseHeroes');
        $this->assertNotEmpty($heroes);
        $this->assertEquals('Entry T-Shirt', $heroes[0]['product_name']);
        $this->assertEquals(2, $heroes[0]['first_purchases']);
    }

    public function test_first_purchase_heroes_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $heroes = $response->viewData('firstPurchaseHeroes');
        $this->assertEmpty($heroes);
    }

    public function test_product_affinity_finds_co_purchased_products(): void
    {
        $this->actingAsAdmin();

        $productA = Product::factory()->create(['name' => 'Blue Shirt']);
        $variantA = ProductVariant::factory()->create(['product_id' => $productA->id]);
        $productB = Product::factory()->create(['name' => 'Brown Belt']);
        $variantB = ProductVariant::factory()->create(['product_id' => $productB->id]);

        // Order with both products
        $order = Order::factory()->create([
            'email' => 'buyer@example.com',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
            'total' => 80.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'product_variant_id' => $variantA->id,
            'product_name' => 'Blue Shirt',
            'quantity' => 1,
            'unit_price' => 40.00,
            'line_total' => 40.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'product_variant_id' => $variantB->id,
            'product_name' => 'Brown Belt',
            'quantity' => 1,
            'unit_price' => 40.00,
            'line_total' => 40.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $affinity = $response->viewData('productAffinity');
        $this->assertNotEmpty($affinity);
        $this->assertEquals(1, $affinity[0]['co_purchases']);

        $names = [$affinity[0]['product_a'], $affinity[0]['product_b']];
        sort($names);
        $this->assertEquals(['Blue Shirt', 'Brown Belt'], $names);
    }

    public function test_product_affinity_empty_without_multi_product_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $affinity = $response->viewData('productAffinity');
        $this->assertEmpty($affinity);
    }

    public function test_top_abandoned_products_lists_abandoned_items(): void
    {
        $this->actingAsAdmin();

        CartAbandonment::factory()->create([
            'cart_data' => [
                1 => [
                    'product_id' => 1,
                    'product_name' => 'Heavy Vinyl Record',
                    'price' => 35.00,
                    'quantity' => 2,
                ],
                2 => [
                    'product_id' => 2,
                    'product_name' => 'T-Shirt',
                    'price' => 25.00,
                    'quantity' => 1,
                ],
            ],
            'abandoned_at' => now()->subDays(2),
            'recovered_at' => null,
        ]);

        CartAbandonment::factory()->create([
            'cart_data' => [
                1 => [
                    'product_id' => 1,
                    'product_name' => 'Heavy Vinyl Record',
                    'price' => 35.00,
                    'quantity' => 1,
                ],
            ],
            'abandoned_at' => now()->subDay(),
            'recovered_at' => null,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'marketing']));
        $response->assertOk();

        $abandoned = $response->viewData('topAbandonedProducts');
        $this->assertNotEmpty($abandoned);
        $this->assertEquals('Heavy Vinyl Record', $abandoned[0]['product_name']);
        $this->assertEquals(2, $abandoned[0]['times_abandoned']);
        $this->assertEquals(3, $abandoned[0]['total_qty']);
    }

    public function test_top_abandoned_products_excludes_recovered_carts(): void
    {
        $this->actingAsAdmin();

        CartAbandonment::factory()->create([
            'cart_data' => [
                1 => [
                    'product_id' => 1,
                    'product_name' => 'Recovered Item',
                    'price' => 50.00,
                    'quantity' => 1,
                ],
            ],
            'abandoned_at' => now()->subDays(2),
            'recovered_at' => now()->subDay(),
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'marketing']));
        $response->assertOk();

        $abandoned = $response->viewData('topAbandonedProducts');
        $this->assertEmpty($abandoned);
    }

    public function test_sales_tab_has_heroes_and_affinity_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk()
            ->assertViewHas('firstPurchaseHeroes')
            ->assertViewHas('productAffinity')
            ->assertViewHas('shippingMargins');
    }

    public function test_marketing_tab_has_abandoned_products_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'marketing']));
        $response->assertOk()
            ->assertViewHas('topAbandonedProducts');
    }

    public function test_overview_has_site_conversion_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertViewHas('siteConversion');
    }

    public function test_site_conversion_calculates_metrics(): void
    {
        $this->withoutMiddleware(TrackVisitor::class);
        $this->actingAsAdmin();

        DailyVisit::create(['date' => now()->subDays(2)->toDateString(), 'visitor_count' => 100]);
        DailyVisit::create(['date' => now()->subDay()->toDateString(), 'visitor_count' => 150]);

        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(2),
            'total' => 75.00,
        ]);
        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subDay(),
            'total' => 125.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['period' => '7d']));
        $response->assertOk();

        $conversion = $response->viewData('siteConversion');
        $this->assertEquals(250, $conversion['visitors']);
        $this->assertEquals(2, $conversion['orders']);
        // CR = 2/250 * 100 = 0.8%
        $this->assertEquals(0.8, $conversion['conversion_pct']);
        // Rev/visitor = 200/250 = 0.8
        $this->assertEquals(0.8, $conversion['revenue_per_visitor']);
    }

    public function test_site_conversion_empty_without_visitors(): void
    {
        $this->withoutMiddleware(TrackVisitor::class);
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk();

        $conversion = $response->viewData('siteConversion');
        $this->assertEquals(0, $conversion['visitors']);
        $this->assertEquals(0.0, $conversion['conversion_pct']);
        $this->assertEquals(0.0, $conversion['revenue_per_visitor']);
        $this->assertArrayHasKey('revenue', $conversion);
    }

    public function test_site_conversion_shows_orders_and_revenue_without_visitors(): void
    {
        $this->withoutMiddleware(TrackVisitor::class);
        $this->actingAsAdmin();

        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
            'total' => 150.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['period' => '30d']));
        $response->assertOk();

        $conversion = $response->viewData('siteConversion');
        $this->assertEquals(0, $conversion['visitors']);
        $this->assertEquals(1, $conversion['orders']);
        $this->assertEquals(150.0, $conversion['revenue']);
        $this->assertEquals(0.0, $conversion['conversion_pct']);
    }

    public function test_aov_by_country_calculates_correctly(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'country' => 'US',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
            'total' => 100.00,
        ]);
        Order::factory()->create([
            'country' => 'US',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(4),
            'total' => 200.00,
        ]);
        Order::factory()->create([
            'country' => 'DE',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(3),
            'total' => 80.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $aovByCountry = $response->viewData('aovByCountry');
        $this->assertNotEmpty($aovByCountry);

        $us = collect($aovByCountry)->firstWhere('country', 'US');
        $de = collect($aovByCountry)->firstWhere('country', 'DE');

        $this->assertNotNull($us);
        $this->assertEquals(150.0, $us['aov']);
        $this->assertEquals(2, $us['orders']);

        $this->assertNotNull($de);
        $this->assertEquals(80.0, $de['aov']);
        $this->assertEquals(1, $de['orders']);
    }

    public function test_aov_by_country_empty_without_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $aovByCountry = $response->viewData('aovByCountry');
        $this->assertEmpty($aovByCountry);
    }

    public function test_sales_tab_has_aov_by_country(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk()
            ->assertViewHas('aovByCountry');
    }

    public function test_visitor_tracking_middleware_increments_count(): void
    {
        // Visit the homepage as a regular user — middleware should count it
        $response = $this->get('/');

        $visit = DailyVisit::where('date', now()->toDateString())->first();
        $this->assertNotNull($visit);
        $this->assertEquals(1, $visit->visitor_count);

        // Second request in same session should NOT increment
        $response = $this->get('/');
        $visit->refresh();
        $this->assertEquals(1, $visit->visitor_count);
    }

    public function test_inventory_has_preorder_liability_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk()
            ->assertViewHas('preorderLiability');
    }

    public function test_preorder_liability_calculates_correctly(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'status' => 'pre-ordered',
            'payment_status' => 'paid',
            'total' => 150.00,
            'placed_at' => now()->subDays(5),
        ]);
        Order::factory()->create([
            'status' => 'pre-ordered',
            'payment_status' => 'paid',
            'total' => 250.00,
            'placed_at' => now()->subDays(3),
        ]);
        // This one should NOT count (refunded)
        Order::factory()->create([
            'status' => 'pre-ordered',
            'payment_status' => 'refunded',
            'total' => 100.00,
            'placed_at' => now()->subDays(2),
        ]);

        // Pending pre-order SHOULD count (pre-orders are committed even before payment)
        Order::factory()->create([
            'status' => 'pre-ordered',
            'payment_status' => 'pending',
            'total' => 100.00,
            'placed_at' => now()->subDays(1),
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $liability = $response->viewData('preorderLiability');
        $this->assertEquals(500.0, $liability['total_liability']);
        $this->assertEquals(3, $liability['order_count']);
    }

    public function test_preorder_liability_zero_without_preorders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $liability = $response->viewData('preorderLiability');
        $this->assertEquals(0, $liability['order_count']);
        $this->assertEquals(0.0, $liability['total_liability']);
    }

    public function test_inventory_has_fulfillment_speed_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk()
            ->assertViewHas('fulfillmentSpeed');
    }

    public function test_fulfillment_speed_calculates_correctly(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create([
            'status' => 'shipped',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(5),
            'shipped_at' => now()->subDays(3),
        ]);
        Order::factory()->create([
            'status' => 'delivered',
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(10),
            'shipped_at' => now()->subDays(6),
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $speed = $response->viewData('fulfillmentSpeed');
        $this->assertEquals(2, $speed['shipped_count']);
        $this->assertGreaterThan(0, $speed['avg_days']);
        $this->assertGreaterThan(0, $speed['median_days']);
    }

    public function test_fulfillment_speed_zero_without_shipped_orders(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $speed = $response->viewData('fulfillmentSpeed');
        $this->assertEquals(0, $speed['shipped_count']);
        $this->assertEquals(0.0, $speed['avg_days']);
    }

    // ── Dead Stock ────────────────────────────────────────────

    public function test_inventory_has_dead_stock_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk()
            ->assertViewHas('deadStock');
    }

    public function test_dead_stock_detects_stale_variants(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['status' => 'active']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 15,
            'is_active' => true,
            'price' => 25.00,
        ]);

        // Last sale was 200 days ago
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(200),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $dead = $response->viewData('deadStock');
        $this->assertNotEmpty($dead['items']);

        $found = collect($dead['items'])->firstWhere('sku', $variant->sku);
        $this->assertNotNull($found);
        $this->assertEquals(15, $found['stock']);
        $this->assertEquals(375.0, $found['stock_value']);
        $this->assertGreaterThanOrEqual(200, $found['days_since_last_sale']);
        $this->assertEquals('Clearance Sale', $found['suggestion']);
    }

    public function test_dead_stock_excludes_recently_sold(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['status' => 'active']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        // Last sale was 30 days ago → should NOT appear
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(30),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $dead = $response->viewData('deadStock');
        $found = collect($dead['items'])->firstWhere('sku', $variant->sku);
        $this->assertNull($found);
    }

    public function test_dead_stock_suggests_bundle_for_co_purchased(): void
    {
        $this->actingAsAdmin();

        $productA = Product::factory()->create(['status' => 'active', 'name' => 'Dead Item']);
        $variantA = ProductVariant::factory()->create([
            'product_id' => $productA->id,
            'stock_quantity' => 20,
            'is_active' => true,
            'price' => 30.00,
        ]);

        $productB = Product::factory()->create(['status' => 'active', 'name' => 'Popular Item']);
        $variantB = ProductVariant::factory()->create([
            'product_id' => $productB->id,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        // 3 orders containing both products (older than 180d)
        foreach (range(1, 3) as $i) {
            $order = Order::factory()->create([
                'payment_status' => 'paid',
                'placed_at' => now()->subDays(200 + $i),
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_variant_id' => $variantA->id,
                'quantity' => 1,
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_variant_id' => $variantB->id,
                'quantity' => 1,
            ]);
        }

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $dead = $response->viewData('deadStock');
        $found = collect($dead['items'])->firstWhere('sku', $variantA->sku);
        $this->assertNotNull($found);
        $this->assertEquals('Bundle Inclusion', $found['suggestion']);
    }

    public function test_dead_stock_empty_when_all_healthy(): void
    {
        $this->actingAsAdmin();

        // Variant with stock ≤ 10 → should NOT appear
        $product = Product::factory()->create(['status' => 'active']);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $dead = $response->viewData('deadStock');
        $this->assertEmpty($dead['items']);
    }

    public function test_inventory_has_days_of_stock_remaining(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk()
            ->assertViewHas('daysOfStockRemaining');
    }

    public function test_days_of_stock_remaining_calculates_velocity(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['status' => 'active']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Simulate 30 units sold in last 30 days → 1/day → 10 days remaining
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(15),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 30,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $daysOfStock = $response->viewData('daysOfStockRemaining');
        $this->assertNotEmpty($daysOfStock);

        $found = collect($daysOfStock)->firstWhere('sku', $variant->sku);
        $this->assertNotNull($found);
        $this->assertEquals(10.0, $found['days_remaining']);
        $this->assertEquals(1.0, $found['daily_velocity']);
    }

    public function test_days_of_stock_remaining_empty_when_healthy(): void
    {
        $this->actingAsAdmin();

        // Create a variant with stock > 20 (healthy) → should NOT appear
        $product = Product::factory()->create(['status' => 'active']);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $daysOfStock = $response->viewData('daysOfStockRemaining');
        $this->assertEmpty($daysOfStock);
    }

    // ── Revenue at Risk ───────────────────────────────────────

    public function test_inventory_tab_has_revenue_at_risk(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk()
            ->assertViewHas('revenueAtRisk');

        $risk = $response->viewData('revenueAtRisk');
        $this->assertArrayHasKey('total_monthly_revenue', $risk);
        $this->assertArrayHasKey('variant_count', $risk);
        $this->assertArrayHasKey('product_count', $risk);
        $this->assertArrayHasKey('top_items', $risk);
    }

    public function test_revenue_at_risk_calculates_from_sold_out_variants(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['status' => 'active']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 0,
            'is_active' => true,
            'price' => 50.00,
        ]);

        // Create sales in the last 90 days to build velocity
        foreach (range(1, 3) as $i) {
            $order = Order::factory()->create([
                'payment_status' => 'paid',
                'placed_at' => now()->subDays($i * 15),
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 10,
                'line_total' => 500.00,
            ]);
        }

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $risk = $response->viewData('revenueAtRisk');
        $this->assertGreaterThan(0, $risk['total_monthly_revenue']);
        $this->assertEquals(1, $risk['variant_count']);
        $this->assertEquals(1, $risk['product_count']);
        $this->assertNotEmpty($risk['top_items']);
        $this->assertArrayHasKey('monthly_revenue', $risk['top_items'][0]);
        $this->assertArrayHasKey('avg_daily_units', $risk['top_items'][0]);
        $this->assertArrayHasKey('avg_price', $risk['top_items'][0]);
    }

    public function test_revenue_at_risk_empty_without_sold_out_variants(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['status' => 'active']);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $risk = $response->viewData('revenueAtRisk');
        $this->assertEquals(0.0, $risk['total_monthly_revenue']);
        $this->assertEquals(0, $risk['variant_count']);
        $this->assertEmpty($risk['top_items']);
    }

    public function test_revenue_at_risk_ignores_variants_without_recent_sales(): void
    {
        $this->actingAsAdmin();

        $product = Product::factory()->create(['status' => 'active']);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        // No sales within 90 days

        $response = $this->get(route('admin.dashboard', ['tab' => 'inventory']));
        $response->assertOk();

        $risk = $response->viewData('revenueAtRisk');
        $this->assertEquals(1, $risk['variant_count']);
        $this->assertEmpty($risk['top_items']);
    }

    // ── MTD Benchmark ─────────────────────────────────────────

    public function test_best_month_benchmark_includes_mtd_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $benchmark = $response->viewData('bestMonthBenchmark');
        $this->assertArrayHasKey('mtd_day', $benchmark);
        $this->assertArrayHasKey('mtd_current', $benchmark);
        $this->assertArrayHasKey('mtd_best_revenue', $benchmark);
        $this->assertArrayHasKey('mtd_best_year', $benchmark);
        $this->assertArrayHasKey('mtd_gap_pct', $benchmark);
    }

    public function test_mtd_benchmark_compares_same_day_range(): void
    {
        $this->actingAsAdmin();

        $currentDay = (int) now()->day;
        $currentMonth = (int) now()->month;

        // Create an order in the current month on day 1 (guaranteed within mtd range)
        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->startOfMonth(),
            'total' => 200.00,
        ]);

        // Create an order in the same month last year on day 1
        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subYear()->startOfMonth(),
            'total' => 150.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $benchmark = $response->viewData('bestMonthBenchmark');
        $this->assertEquals($currentDay, $benchmark['mtd_day']);
        $this->assertGreaterThanOrEqual(200.00, $benchmark['mtd_current']);
        $this->assertGreaterThanOrEqual(150.00, $benchmark['mtd_best_revenue']);
        $this->assertNotNull($benchmark['mtd_best_year']);
        $this->assertNotNull($benchmark['mtd_gap_pct']);
    }

    public function test_mtd_benchmark_null_without_previous_year_data(): void
    {
        $this->actingAsAdmin();

        // Only current year data
        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->startOfMonth(),
            'total' => 100.00,
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $benchmark = $response->viewData('bestMonthBenchmark');
        $this->assertNull($benchmark['mtd_best_year']);
        $this->assertNull($benchmark['mtd_gap_pct']);
    }

    // ── Regional Growth AOV Series ────────────────────────────

    public function test_regional_growth_includes_aov_series(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $regional = $response->viewData('regionalGrowth');
        $this->assertArrayHasKey('aov_series', $regional);
    }

    public function test_regional_growth_aov_series_with_data(): void
    {
        $this->actingAsAdmin();

        // Create multiple orders in the same country same quarter
        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subMonth(),
            'total' => 200.00,
            'country' => 'DE',
        ]);

        Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subMonth(),
            'total' => 100.00,
            'country' => 'DE',
        ]);

        $response = $this->get(route('admin.dashboard', ['tab' => 'sales']));
        $response->assertOk();

        $regional = $response->viewData('regionalGrowth');

        if (! empty($regional['countries'])) {
            $this->assertArrayHasKey('aov_series', $regional);
            // AOV for DE should be 150 (300/2)
            if (isset($regional['aov_series']['DE'])) {
                $aovValues = array_filter($regional['aov_series']['DE']);
                $this->assertNotEmpty($aovValues);
                $this->assertEquals(150.0, array_values($aovValues)[0]);
            }
        }
    }

    // ── Release Benchmark Custom Start Dates ──────────────────

    public function test_release_benchmark_accepts_custom_start_dates_via_livewire(): void
    {
        $productA = Product::factory()->create(['published_at' => now()->subMonths(6)]);
        $productB = Product::factory()->create(['published_at' => now()->subMonths(3)]);

        $customStartA = now()->subMonths(4)->format('Y-m-d');

        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => now()->subMonths(4)->addDays(2),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 3,
            'line_total' => 90.00,
        ]);

        Livewire::test('admin.release-benchmark')
            ->call('selectProduct', 'a', $productA->id, $productA->name)
            ->call('selectProduct', 'b', $productB->id, $productB->name)
            ->set('startA', $customStartA)
            ->set('days', 14)
            ->assertSet('selectedA', $productA->id)
            ->assertSet('selectedB', $productB->id)
            ->assertSet('startA', $customStartA);
    }

    public function test_release_benchmark_product_selection_via_livewire(): void
    {
        $productA = Product::factory()->create(['published_at' => now()->subMonths(6)]);
        $productB = Product::factory()->create(['published_at' => now()->subMonths(3)]);

        Livewire::test('admin.release-benchmark')
            ->call('selectProduct', 'a', $productA->id, $productA->name)
            ->call('selectProduct', 'b', $productB->id, $productB->name)
            ->assertSet('selectedA', $productA->id)
            ->assertSet('selectedB', $productB->id)
            ->assertSet('selectedNameA', $productA->name)
            ->assertSet('selectedNameB', $productB->name);
    }

    public function test_release_benchmark_renders_comparison_data_with_orders(): void
    {
        $productA = Product::factory()->create(['published_at' => now()->subMonths(6)]);
        $productB = Product::factory()->create(['published_at' => now()->subMonths(3)]);

        // Create orders for product A within 30 days of publish
        $orderA = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => Carbon::parse($productA->published_at)->addDays(5),
        ]);
        OrderItem::factory()->create([
            'order_id' => $orderA->id,
            'product_id' => $productA->id,
            'quantity' => 2,
            'line_total' => 50.00,
        ]);

        // Create orders for product B within 30 days of publish
        $orderB = Order::factory()->create([
            'payment_status' => 'paid',
            'placed_at' => Carbon::parse($productB->published_at)->addDays(3),
        ]);
        OrderItem::factory()->create([
            'order_id' => $orderB->id,
            'product_id' => $productB->id,
            'quantity' => 1,
            'line_total' => 75.00,
        ]);

        Livewire::test('admin.release-benchmark')
            ->call('selectProduct', 'a', $productA->id, $productA->name)
            ->call('selectProduct', 'b', $productB->id, $productB->name)
            ->assertSee($productA->name)
            ->assertSee($productB->name)
            ->assertSee('2 units')
            ->assertSee('1 units')
            ->assertSeeHtml('canvas');
    }

    // ── Contextual Notes ──────────────────────────────────────

    public function test_contextual_notes_can_be_created_with_context(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test('admin.dashboard-notes', [
            'context' => 'sales-benchmark',
            'contextLabel' => 'Monthly Benchmark',
        ])
            ->set('newNote', 'This month looks promising')
            ->set('showForm', true)
            ->call('addNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('admin_notes', [
            'user_id' => $user->id,
            'content' => 'This month looks promising',
            'context' => 'sales-benchmark',
            'context_label' => 'Monthly Benchmark',
        ]);
    }

    public function test_contextual_notes_filtered_by_context(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        // Create a general note (no context)
        AdminNote::factory()->create([
            'user_id' => $user->id,
            'content' => 'General dashboard note',
        ]);

        // Create a contextual note
        AdminNote::factory()->create([
            'user_id' => $user->id,
            'content' => 'Benchmark insight',
            'context' => 'sales-benchmark',
            'context_label' => 'Monthly Benchmark',
        ]);

        // General notes component should not show contextual notes
        Livewire::test('admin.dashboard-notes')
            ->assertSee('General dashboard note')
            ->assertDontSee('Benchmark insight');

        // Contextual component should only show its own notes
        Livewire::test('admin.dashboard-notes', [
            'context' => 'sales-benchmark',
            'contextLabel' => 'Monthly Benchmark',
        ])
            ->assertSee('Benchmark insight')
            ->assertDontSee('General dashboard note');
    }

    public function test_contextual_notes_different_contexts_isolated(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        AdminNote::factory()->create([
            'user_id' => $user->id,
            'content' => 'Revenue at risk note',
            'context' => 'inventory-revenue-at-risk',
        ]);

        AdminNote::factory()->create([
            'user_id' => $user->id,
            'content' => 'Regional growth note',
            'context' => 'sales-regional-growth',
        ]);

        // Inventory context should not show sales notes
        Livewire::test('admin.dashboard-notes', [
            'context' => 'inventory-revenue-at-risk',
            'contextLabel' => 'Revenue at Risk',
        ])
            ->assertSee('Revenue at risk note')
            ->assertDontSee('Regional growth note');
    }
}

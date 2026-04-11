<?php

namespace Tests\Feature\Admin;

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
}

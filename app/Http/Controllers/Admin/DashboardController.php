<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** @var array<string, int|null> */
    private const PERIOD_PRESETS = [
        '7d' => 7,
        '14d' => 14,
        '30d' => 30,
        '90d' => 90,
        'year' => 365,
        'all' => null,
    ];

    public function index(Request $request, DashboardStatsService $stats): View
    {
        $tab = $request->query('tab', 'overview');
        $period = $request->query('period', '30d');
        $compare = $request->boolean('compare', false);
        $compareMode = $request->query('compare_mode', 'previous_period');

        if (! in_array($compareMode, ['previous_period', 'yoy'])) {
            $compareMode = 'previous_period';
        }

        // Custom date range
        $customFrom = $request->query('custom_from');
        $customTo = $request->query('custom_to');
        $from = null;
        $to = null;

        if ($customFrom && $customTo) {
            try {
                $from = Carbon::parse($customFrom)->startOfDay();
                $to = Carbon::parse($customTo)->endOfDay();
                $period = 'custom';
            } catch (\Exception) {
                $from = null;
                $to = null;
                $customFrom = null;
                $customTo = null;
            }
        }

        if ($period !== 'custom') {
            if (! array_key_exists($period, self::PERIOD_PRESETS)) {
                $period = '30d';
            }

            $days = self::PERIOD_PRESETS[$period];
            $from = $days ? Carbon::today()->subDays($days) : null;
            $to = $days ? Carbon::now() : null;
        }

        $prevFrom = null;
        $prevTo = null;

        if ($compare && $from) {
            if ($compareMode === 'yoy') {
                $prevFrom = $from->copy()->subYear();
                $prevTo = $to->copy()->subYear();
            } else {
                $days = (int) $from->diffInDays($to);
                $prevTo = $from->copy()->subSecond();
                $prevFrom = $from->copy()->subDays($days);
            }
        }

        // Release benchmark params (sales tab only)
        $benchmarkA = $request->integer('benchmark_a');
        $benchmarkB = $request->integer('benchmark_b');
        $benchmarkDays = $request->integer('benchmark_days', 30);

        if ($benchmarkDays < 7 || $benchmarkDays > 90) {
            $benchmarkDays = 30;
        }

        // Custom launch start dates for release benchmark
        $benchmarkStartA = $request->query('benchmark_start_a');
        $benchmarkStartB = $request->query('benchmark_start_b');

        $data = [
            'tab' => $tab,
            'period' => $period,
            'compare' => $compare,
            'compareMode' => $compareMode,
            'customFrom' => $customFrom,
            'customTo' => $customTo,
            'periodLabel' => $this->periodLabel($period, $from, $to),
            'benchmarkA' => $benchmarkA,
            'benchmarkB' => $benchmarkB,
            'benchmarkDays' => $benchmarkDays,
            'benchmarkStartA' => $benchmarkStartA,
            'benchmarkStartB' => $benchmarkStartB,
        ];

        match ($tab) {
            'sales' => $data += $this->salesTab($stats, $from, $to, $prevFrom, $prevTo, $compare, $benchmarkA, $benchmarkB, $benchmarkDays, $benchmarkStartA, $benchmarkStartB),
            'inventory' => $data += [
                'stockHealth' => $stats->stockHealth(),
                'stockAlertDemand' => $stats->stockAlertDemand(),
                'productStatus' => $stats->productStatusBreakdown(),
                'daysOfStockRemaining' => $stats->daysOfStockRemaining(),
                'revenueAtRisk' => $stats->revenueAtRisk(),
            ],
            'promotions' => $data += $this->promotionsTab($stats, $from, $to, $prevFrom, $prevTo, $compare),
            'customers' => $data += $this->customersTab($stats, $from, $to, $prevFrom, $prevTo, $compare),
            default => $data += $this->overviewTab($stats, $from, $to, $prevFrom, $prevTo, $compare),
        };

        return view('admin.dashboard', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to, ?Carbon $prevFrom, ?Carbon $prevTo, bool $compare): array
    {
        $data = [
            'overview' => $stats->overviewStats($from, $to),
            'recentOrders' => $stats->recentOrders(),
            'revenueOverTime' => $stats->revenueOverTime($from, $to),
            'ordersByStatus' => $stats->ordersByStatus($from, $to),
            'siteConversion' => $stats->siteConversionRate($from, $to),
            'preorderLiability' => $stats->preorderLiability(),
            'fulfillmentSpeed' => $stats->fulfillmentSpeed($from, $to),
        ];

        if ($compare && $prevFrom) {
            $prev = $stats->overviewStats($prevFrom, $prevTo);
            $data['deltas'] = [
                'revenue' => DashboardStatsService::delta($data['overview']['revenue'], $prev['revenue']),
                'total_orders' => DashboardStatsService::delta($data['overview']['total_orders'], $prev['total_orders']),
            ];
            $data['prevRevenueOverTime'] = $stats->revenueOverTime($prevFrom, $prevTo);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function salesTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to, ?Carbon $prevFrom, ?Carbon $prevTo, bool $compare, int $benchmarkA, int $benchmarkB, int $benchmarkDays, ?string $benchmarkStartA = null, ?string $benchmarkStartB = null): array
    {
        $aov = $stats->averageOrderValue($from, $to);
        $repeatRate = $stats->repeatCustomerRate($from, $to);
        $itemsPerOrder = $stats->itemsPerOrder($from, $to);

        $data = [
            'revenueOverTime' => $stats->revenueOverTime($from, $to),
            'ordersByStatus' => $stats->ordersByStatus($from, $to),
            'revenueByCountry' => $stats->revenueByCountry(10, $from, $to),
            'aovByCountry' => $stats->aovByCountry(10, $from, $to),
            'topProducts' => $stats->topSellingProducts(10, $from, $to),
            'aov' => $aov,
            'repeatRate' => $repeatRate,
            'itemsPerOrder' => $itemsPerOrder,
            'yearlyRevenue' => $stats->monthlyRevenueByYear(),
            'bestMonthBenchmark' => $stats->bestMonthBenchmark(),
            'benchmarkableProducts' => $stats->benchmarkableProducts(),
            'releaseBenchmark' => ($benchmarkA && $benchmarkB) ? $stats->releaseBenchmark($benchmarkA, $benchmarkB, $benchmarkDays, $benchmarkStartA, $benchmarkStartB) : null,
            'regionalGrowth' => $stats->regionalGrowthTrend(),
            'productDecay' => $stats->productDecayTracking(),
            'firstPurchaseHeroes' => $stats->firstPurchaseHeroes(),
            'productAffinity' => $stats->productAffinity(),
        ];

        if ($compare && $prevFrom) {
            $prevAov = $stats->averageOrderValue($prevFrom, $prevTo);
            $prevRepeat = $stats->repeatCustomerRate($prevFrom, $prevTo);
            $prevItems = $stats->itemsPerOrder($prevFrom, $prevTo);
            $data['deltas'] = [
                'aov' => DashboardStatsService::delta($aov, $prevAov),
                'repeat_pct' => DashboardStatsService::delta($repeatRate['repeat_pct'], $prevRepeat['repeat_pct']),
                'items_per_order' => DashboardStatsService::delta($itemsPerOrder, $prevItems),
            ];
            $data['prevRevenueOverTime'] = $stats->revenueOverTime($prevFrom, $prevTo);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionsTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to, ?Carbon $prevFrom, ?Carbon $prevTo, bool $compare): array
    {
        $impact = $stats->discountMarginImpact($from, $to);
        $recovery = $stats->cartRecoveryFunnel($from, $to);

        $data = [
            'couponLeaderboard' => $stats->couponLeaderboard(10, $from, $to),
            'discountImpact' => $impact,
            'cartRecovery' => $recovery,
            'topAbandonedProducts' => $stats->topAbandonedProducts(10, $from, $to),
        ];

        if ($compare && $prevFrom) {
            $prevImpact = $stats->discountMarginImpact($prevFrom, $prevTo);
            $prevRecovery = $stats->cartRecoveryFunnel($prevFrom, $prevTo);
            $data['deltas'] = [
                'discount_pct' => DashboardStatsService::delta($impact['discount_pct'], $prevImpact['discount_pct']),
                'recovery_pct' => DashboardStatsService::delta($recovery['recovery_pct'], $prevRecovery['recovery_pct']),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function customersTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to, ?Carbon $prevFrom, ?Carbon $prevTo, bool $compare): array
    {
        $custStats = $stats->customerStats($from, $to);

        $data = [
            'customerStats' => $custStats,
            'customerGeo' => $stats->customerGeography(10, $from, $to),
            'tagFollows' => $stats->tagFollowPopularity(),
            'cohortRetention' => $stats->cohortRetentionHistory(),
            'aovBreakdown' => $stats->aovBreakdown(),
            'waitlistConversion' => $stats->waitlistConversionBenchmark(),
            'rfmSegmentation' => $stats->rfmSegmentation(),
            'clv' => $stats->customerLifetimeValue(),
            'vipChurn' => $stats->vipChurnWarning(),
        ];

        if ($compare && $prevFrom) {
            $prevCust = $stats->customerStats($prevFrom, $prevTo);
            $data['deltas'] = [
                'new_in_period' => DashboardStatsService::delta($custStats['new_in_period'], $prevCust['new_in_period']),
            ];
        }

        return $data;
    }

    private function periodLabel(string $period, ?Carbon $from = null, ?Carbon $to = null): string
    {
        if ($period === 'custom' && $from && $to) {
            return $from->format('M j, Y').' – '.$to->format('M j, Y');
        }

        return match ($period) {
            '7d' => 'Last 7 Days',
            '14d' => 'Last 14 Days',
            '30d' => 'Last 30 Days',
            '90d' => 'Last 90 Days',
            'year' => 'Last Year',
            'all' => 'All Time',
            default => 'Last 30 Days',
        };
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** @var list<string> */
    private const TABS = ['overview', 'sales', 'audience', 'inventory', 'marketing'];

    /** @var array<string, int|null> */
    private const PERIOD_PRESETS = [
        '1d' => 1,
        '7d' => 7,
        '14d' => 14,
        '30d' => 30,
        '90d' => 90,
        'year' => 365,
        'all' => null,
    ];

    /** @var list<string> */
    private const COMPARE_MODES = ['previous_period', 'yoy', 'custom_interval', 'custom_range'];

    /** @var list<string> */
    private const COMPARE_INTERVAL_UNITS = ['day', 'week', 'month', 'quarter', 'year'];

    public function index(Request $request, DashboardStatsService $stats): View
    {
        $tab = $request->query('tab', 'overview');

        if (! in_array($tab, self::TABS, true)) {
            $tab = 'overview';
        }

        $period = $request->query('period', '30d');
        $compare = $request->boolean('compare', false);
        $compareMode = $request->query('compare_mode', 'previous_period');
        $compareIntervalUnit = $request->query('compare_interval_unit', 'week');
        $compareIntervalValue = $request->integer('compare_interval_value', 1);
        $compareCustomFrom = $request->query('compare_custom_from');
        $compareCustomTo = $request->query('compare_custom_to');

        if (! in_array($compareMode, self::COMPARE_MODES, true)) {
            $compareMode = 'previous_period';
        }

        if (! in_array($compareIntervalUnit, self::COMPARE_INTERVAL_UNITS, true)) {
            $compareIntervalUnit = 'week';
        }

        if ($compareIntervalValue < 1) {
            $compareIntervalValue = 1;
        }

        if ($compareIntervalValue > 365) {
            $compareIntervalValue = 365;
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

            if ($period === '1d') {
                // Rolling 24-hour window so the data matches the "Last 24 Hours" label.
                $from = Carbon::now()->subDay();
                $to = Carbon::now();
            } else {
                $from = $days ? Carbon::today()->subDays($days) : null;
                $to = $days ? Carbon::now() : null;
            }
        }

        $prevFrom = null;
        $prevTo = null;

        if ($compare && $from) {
            if ($compareMode === 'yoy') {
                $prevFrom = $from->copy()->subYear();
                $prevTo = $to->copy()->subYear();
            } elseif ($compareMode === 'custom_interval') {
                $prevFrom = $this->shiftDateByInterval($from->copy(), $compareIntervalValue, $compareIntervalUnit);
                $prevTo = $this->shiftDateByInterval($to->copy(), $compareIntervalValue, $compareIntervalUnit);
            } elseif ($compareMode === 'custom_range') {
                [$parsedCompareFrom, $parsedCompareTo] = $this->parseCustomCompareRange($compareCustomFrom, $compareCustomTo);

                if ($parsedCompareFrom && $parsedCompareTo) {
                    $prevFrom = $parsedCompareFrom;
                    $prevTo = $parsedCompareTo;
                    $compareCustomFrom = $parsedCompareFrom->toDateString();
                    $compareCustomTo = $parsedCompareTo->toDateString();
                } else {
                    [$prevFrom, $prevTo] = $this->previousPeriodRange($from, $to);
                    $compareCustomFrom = $prevFrom->toDateString();
                    $compareCustomTo = $prevTo->toDateString();
                }
            } else {
                [$prevFrom, $prevTo] = $this->previousPeriodRange($from, $to);
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
            'compareIntervalUnit' => $compareIntervalUnit,
            'compareIntervalValue' => $compareIntervalValue,
            'compareCustomFrom' => $compareCustomFrom,
            'compareCustomTo' => $compareCustomTo,
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
            'audience' => $data += $this->audienceTab($stats, $from, $to, $prevFrom, $prevTo, $compare),
            'inventory' => $data += $this->inventoryTab($stats, $from, $to),
            'marketing' => $data += $this->marketingTab($stats, $from, $to, $prevFrom, $prevTo, $compare),
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
        ];

        if ($compare && $prevFrom) {
            $prev = $stats->overviewStats($prevFrom, $prevTo);
            $data['deltas'] = [
                'revenue' => DashboardStatsService::delta($data['overview']['revenue'], $prev['revenue']),
                'net_revenue' => DashboardStatsService::delta($data['overview']['net_revenue'], $prev['net_revenue']),
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
        $itemsPerOrder = $stats->itemsPerOrder($from, $to);
        $discountImpact = $stats->discountMarginImpact($from, $to);

        $data = [
            'revenueOverTime' => $stats->revenueOverTime($from, $to),
            'ordersByStatus' => $stats->ordersByStatus($from, $to),
            'revenueByCountry' => $stats->revenueByCountry(50, $from, $to),
            'aovByCountry' => $stats->aovByCountry(50, $from, $to),
            'customerGeo' => $stats->customerGeography(30, $from, $to),
            'topProducts' => $stats->topSellingProducts(50, $from, $to),
            'aov' => $aov,
            'itemsPerOrder' => $itemsPerOrder,
            'discountImpact' => $discountImpact,
            'aovBreakdown' => $stats->aovBreakdown(),
            'yearlyRevenue' => $stats->monthlyRevenueByYear(),
            'bestMonthBenchmark' => $stats->bestMonthBenchmark(),
            'regionalGrowth' => $stats->regionalGrowthTrend(),
            'productDecay' => $stats->productDecayTracking(30),
            'firstPurchaseHeroes' => $stats->firstPurchaseHeroes(30),
            'productAffinity' => $stats->productAffinity(30),
            'shippingMargins' => $stats->shippingMargins(30),
        ];

        if ($compare && $prevFrom) {
            $prevAov = $stats->averageOrderValue($prevFrom, $prevTo);
            $prevItems = $stats->itemsPerOrder($prevFrom, $prevTo);
            $prevImpact = $stats->discountMarginImpact($prevFrom, $prevTo);
            $data['deltas'] = [
                'aov' => DashboardStatsService::delta($aov, $prevAov),
                'items_per_order' => DashboardStatsService::delta($itemsPerOrder, $prevItems),
                'discount_pct' => DashboardStatsService::delta($discountImpact['discount_pct'], $prevImpact['discount_pct']),
            ];
            $data['prevRevenueOverTime'] = $stats->revenueOverTime($prevFrom, $prevTo);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function marketingTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to, ?Carbon $prevFrom, ?Carbon $prevTo, bool $compare): array
    {
        $recovery = $stats->cartRecoveryFunnel($from, $to);
        $custStats = $stats->customerStats($from, $to);

        $data = [
            'newsletterCount' => $custStats['total_newsletter'],
            'couponLeaderboard' => $stats->couponLeaderboard(30, $from, $to),
            'cartRecovery' => $recovery,
            'topAbandonedProducts' => $stats->topAbandonedProducts(30, $from, $to),
            'waitlistConversion' => $stats->waitlistConversionBenchmark(),
        ];

        if ($compare && $prevFrom) {
            $prevRecovery = $stats->cartRecoveryFunnel($prevFrom, $prevTo);
            $data['deltas'] = [
                'recovery_pct' => DashboardStatsService::delta($recovery['recovery_pct'], $prevRecovery['recovery_pct']),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function audienceTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to, ?Carbon $prevFrom, ?Carbon $prevTo, bool $compare): array
    {
        $custStats = $stats->customerStats($from, $to);
        $repeatRate = $stats->repeatCustomerRate($from, $to);

        $data = [
            'customerStats' => $custStats,
            'repeatRate' => $repeatRate,
            'tagFollows' => $stats->tagFollowPopularity(30),
            'cohortRetention' => $stats->cohortRetentionHistory(),
            'rfmSegmentation' => $stats->rfmSegmentation(),
            'clv' => $stats->customerLifetimeValue(),
            'vipChurn' => $stats->vipChurnWarning(),
        ];

        if ($compare && $prevFrom) {
            $prevCust = $stats->customerStats($prevFrom, $prevTo);
            $prevRepeat = $stats->repeatCustomerRate($prevFrom, $prevTo);
            $data['deltas'] = [
                'active_in_period' => DashboardStatsService::delta($custStats['active_in_period'], $prevCust['active_in_period']),
                'new_in_period' => DashboardStatsService::delta($custStats['new_in_period'], $prevCust['new_in_period']),
                'repeat_pct' => DashboardStatsService::delta($repeatRate['repeat_pct'], $prevRepeat['repeat_pct']),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryTab(DashboardStatsService $stats, ?Carbon $from, ?Carbon $to): array
    {
        return [
            'stockHealth' => $stats->stockHealth(),
            'stockAlertDemand' => $stats->stockAlertDemand(30),
            'productStatus' => $stats->productStatusBreakdown(),
            'daysOfStockRemaining' => $stats->daysOfStockRemaining(50),
            'revenueAtRisk' => $stats->revenueAtRisk(50),
            'preorderLiability' => $stats->preorderLiability(),
            'fulfillmentSpeed' => $stats->fulfillmentSpeed($from, $to),
            'deadStock' => $stats->deadStock(50),
        ];
    }

    private function periodLabel(string $period, ?Carbon $from = null, ?Carbon $to = null): string
    {
        if ($period === 'custom' && $from && $to) {
            return $from->format('M j, Y').' – '.$to->format('M j, Y');
        }

        return match ($period) {
            '1d' => 'Last 24 Hours',
            '7d' => 'Last 7 Days',
            '14d' => 'Last 14 Days',
            '30d' => 'Last 30 Days',
            '90d' => 'Last 90 Days',
            'year' => 'Last Year',
            'all' => 'All Time',
            default => 'Last 30 Days',
        };
    }

    /**
     * Adjacent previous window with exactly the same duration as [from, to].
     *
     * Duration is measured in seconds (rounded up) rather than truncated days:
     * a day-aligned custom range (e.g. Apr 1–30) shifts back by exactly 30 days
     * instead of 29, and preset windows — which include a partial "today" — are
     * compared against an equally long window instead of N whole days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriodRange(Carbon $from, Carbon $to): array
    {
        $durationSeconds = (int) ceil($from->diffInSeconds($to));

        return [
            $from->copy()->subSeconds($durationSeconds),
            $from->copy()->subSecond(),
        ];
    }

    private function shiftDateByInterval(Carbon $date, int $value, string $unit): Carbon
    {
        return match ($unit) {
            'day' => $date->subDays($value),
            'week' => $date->subWeeks($value),
            'month' => $date->subMonthsNoOverflow($value),
            'quarter' => $date->subMonthsNoOverflow($value * 3),
            'year' => $date->subYearsNoOverflow($value),
            default => $date->subWeeks($value),
        };
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function parseCustomCompareRange(?string $from, ?string $to): array
    {
        if (! $from || ! $to) {
            return [null, null];
        }

        try {
            $parsedFrom = Carbon::parse($from)->startOfDay();
            $parsedTo = Carbon::parse($to)->endOfDay();

            if ($parsedFrom->gt($parsedTo)) {
                return [null, null];
            }

            return [$parsedFrom, $parsedTo];
        } catch (\Exception) {
            return [null, null];
        }
    }
}

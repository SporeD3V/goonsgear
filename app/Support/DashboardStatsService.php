<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /** @var list<string> */
    private const PAID_STATUSES = ['paid', 'completed'];

    // ── Helpers ────────────────────────────────────────────────

    private function periodCacheKey(string $base, ?Carbon $from, ?Carbon $to): string
    {
        if ($from === null && $to === null) {
            return "dashboard:{$base}:all";
        }

        return "dashboard:{$base}:{$from->format('Ymd')}:{$to->format('Ymd')}";
    }

    /**
     * @param  Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    private function applyPeriod($query, ?Carbon $from, ?Carbon $to, string $column = 'placed_at')
    {
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }
        if ($to !== null) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }

    /**
     * Compute percentage delta between current and previous values.
     */
    public static function delta(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    // ── Overview Tab ──────────────────────────────────────────

    /**
     * @return array{total_orders: int, pending_orders: int, revenue: float, orders_today: int, low_stock: int, out_of_stock: int, stock_alert_waiting: int, total_products: int, active_products: int}
     */
    public function overviewStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('overview', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            return [
                'total_orders' => (int) $this->applyPeriod(Order::query(), $from, $to)->count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'revenue' => (float) $this->applyPeriod(
                    Order::whereIn('payment_status', self::PAID_STATUSES), $from, $to
                )->sum('total'),
                'orders_today' => Order::whereDate('placed_at', Carbon::today())->count(),
                'low_stock' => ProductVariant::where('stock_quantity', '>', 0)
                    ->where('stock_quantity', '<=', 5)
                    ->count(),
                'out_of_stock' => ProductVariant::where('stock_quantity', 0)->count(),
                'stock_alert_waiting' => StockAlertSubscription::where('is_active', true)
                    ->whereNull('notified_at')
                    ->count(),
                'total_products' => Product::count(),
                'active_products' => Product::where('status', 'active')->count(),
            ];
        });
    }

    public function recentOrders(int $limit = 10): Collection
    {
        return Order::latest('placed_at')->take($limit)->get();
    }

    // ── Sales Tab ─────────────────────────────────────────────

    /**
     * @return array<int, array{day: string, revenue: float, gross: float, discounts: float, order_count: int}>
     */
    public function revenueOverTime(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('revenue', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $query = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to);

            $data = $query
                ->selectRaw('DATE(placed_at) as day')
                ->selectRaw('SUM(total) as revenue')
                ->selectRaw('SUM(subtotal) as gross')
                ->selectRaw('SUM(discount_total + regional_discount_total + bundle_discount_total) as discounts')
                ->selectRaw('COUNT(*) as order_count')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->keyBy('day');

            if ($from && $to) {
                $result = [];
                $current = $from->copy()->startOfDay();
                $end = $to->copy()->startOfDay();

                while ($current->lte($end)) {
                    $dayKey = $current->format('Y-m-d');
                    $row = $data->get($dayKey);
                    $result[] = [
                        'day' => $dayKey,
                        'revenue' => (float) ($row->revenue ?? 0),
                        'gross' => (float) ($row->gross ?? 0),
                        'discounts' => (float) ($row->discounts ?? 0),
                        'order_count' => (int) ($row->order_count ?? 0),
                    ];
                    $current->addDay();
                }

                return $result;
            }

            return $data->values()->map(fn ($row) => [
                'day' => $row->day,
                'revenue' => (float) $row->revenue,
                'gross' => (float) $row->gross,
                'discounts' => (float) $row->discounts,
                'order_count' => (int) $row->order_count,
            ])->all();
        });
    }

    /**
     * @return array<string, int>
     */
    public function ordersByStatus(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('orders-by-status', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $query = DB::table('orders');
            $this->applyPeriod($query, $from, $to);

            return $query
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all();
        });
    }

    /**
     * @return array<int, array{country: string, revenue: float, count: int}>
     */
    public function revenueByCountry(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('revenue-by-country', $from, $to);

        return Cache::remember($key, 300, function () use ($limit, $from, $to): array {
            $query = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to);

            return $query
                ->selectRaw('country, SUM(total) as revenue, COUNT(*) as count')
                ->groupBy('country')
                ->orderByDesc('revenue')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'country' => $row->country,
                    'revenue' => (float) $row->revenue,
                    'count' => (int) $row->count,
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array{name: string, units: int, revenue: float}>
     */
    public function topSellingProducts(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('top-products', $from, $to);

        return Cache::remember($key, 300, function () use ($limit, $from, $to): array {
            $query = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to, 'orders.placed_at');

            return $query
                ->selectRaw('order_items.product_name as name, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
                ->groupBy('order_items.product_id', 'order_items.product_name')
                ->orderByDesc('units')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'units' => (int) $row->units,
                    'revenue' => (float) $row->revenue,
                ])
                ->all();
        });
    }

    public function averageOrderValue(?Carbon $from = null, ?Carbon $to = null): float
    {
        $key = $this->periodCacheKey('aov', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): float {
            return (float) $this->applyPeriod(
                Order::whereIn('payment_status', self::PAID_STATUSES), $from, $to
            )->avg('total');
        });
    }

    /**
     * @return array{one_time: int, two_orders: int, three_plus: int, total: int, repeat_pct: float}
     */
    public function repeatCustomerRate(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('repeat-rate', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $query = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to);

            $counts = $query
                ->selectRaw('email, COUNT(*) as order_count')
                ->groupBy('email')
                ->get();

            $oneTime = $counts->where('order_count', 1)->count();
            $twoOrders = $counts->where('order_count', 2)->count();
            $threePlus = $counts->where('order_count', '>=', 3)->count();
            $total = $counts->count();

            return [
                'one_time' => $oneTime,
                'two_orders' => $twoOrders,
                'three_plus' => $threePlus,
                'total' => $total,
                'repeat_pct' => $total > 0 ? round(($twoOrders + $threePlus) / $total * 100, 1) : 0,
            ];
        });
    }

    public function itemsPerOrder(?Carbon $from = null, ?Carbon $to = null): float
    {
        $key = $this->periodCacheKey('items-per-order', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): float {
            $query = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to, 'orders.placed_at');

            $row = $query
                ->selectRaw('SUM(order_items.quantity) as total_items')
                ->selectRaw('COUNT(DISTINCT order_items.order_id) as total_orders')
                ->first();

            $totalOrders = (int) ($row->total_orders ?? 0);

            return $totalOrders > 0 ? round((float) $row->total_items / $totalOrders, 1) : 0;
        });
    }

    // ── Inventory Tab ─────────────────────────────────────────

    /**
     * @return array{out_of_stock: int, critical: int, low: int, healthy: int, overstocked: int}
     */
    public function stockHealth(): array
    {
        return Cache::remember('dashboard:stock-health', 300, function (): array {
            $row = DB::table('product_variants')
                ->where('is_active', true)
                ->selectRaw('SUM(stock_quantity = 0) as out_of_stock')
                ->selectRaw('SUM(stock_quantity BETWEEN 1 AND 5) as critical')
                ->selectRaw('SUM(stock_quantity BETWEEN 6 AND 20) as low')
                ->selectRaw('SUM(stock_quantity BETWEEN 21 AND 100) as healthy')
                ->selectRaw('SUM(stock_quantity > 100) as overstocked')
                ->first();

            return [
                'out_of_stock' => (int) ($row->out_of_stock ?? 0),
                'critical' => (int) ($row->critical ?? 0),
                'low' => (int) ($row->low ?? 0),
                'healthy' => (int) ($row->healthy ?? 0),
                'overstocked' => (int) ($row->overstocked ?? 0),
            ];
        });
    }

    /**
     * @return array<int, array{sku: string, product: string, variant: string, waiting: int}>
     */
    public function stockAlertDemand(int $limit = 15): array
    {
        return Cache::remember('dashboard:stock-alert-demand', 300, function () use ($limit): array {
            return DB::table('stock_alert_subscriptions')
                ->join('product_variants as pv', 'pv.id', '=', 'stock_alert_subscriptions.product_variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where('stock_alert_subscriptions.is_active', true)
                ->whereNull('stock_alert_subscriptions.notified_at')
                ->selectRaw('pv.sku, p.name as product, pv.name as variant, COUNT(*) as waiting')
                ->groupBy('stock_alert_subscriptions.product_variant_id', 'pv.sku', 'p.name', 'pv.name')
                ->orderByDesc('waiting')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'sku' => $row->sku,
                    'product' => $row->product,
                    'variant' => $row->variant,
                    'waiting' => (int) $row->waiting,
                ])
                ->all();
        });
    }

    /**
     * @return array<string, int>
     */
    public function productStatusBreakdown(): array
    {
        return Cache::remember('dashboard:product-status', 300, function (): array {
            return DB::table('products')
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all();
        });
    }

    // ── Promotions Tab ────────────────────────────────────────

    /**
     * @return array<int, array{code: string, times_used: int, total_discounted: float, avg_discount: float}>
     */
    public function couponLeaderboard(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('coupon-leaderboard', $from, $to);

        return Cache::remember($key, 300, function () use ($limit, $from, $to): array {
            $query = DB::table('order_coupon_usages');

            if ($from || $to) {
                $query->join('orders', 'orders.id', '=', 'order_coupon_usages.order_id');
                $this->applyPeriod($query, $from, $to, 'orders.placed_at');
            }

            return $query
                ->selectRaw('order_coupon_usages.coupon_code as code, COUNT(*) as times_used, SUM(order_coupon_usages.discount_total) as total_discounted, AVG(order_coupon_usages.discount_total) as avg_discount')
                ->groupByRaw('order_coupon_usages.coupon_code')
                ->orderByDesc('total_discounted')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code,
                    'times_used' => (int) $row->times_used,
                    'total_discounted' => (float) $row->total_discounted,
                    'avg_discount' => round((float) $row->avg_discount, 2),
                ])
                ->all();
        });
    }

    /**
     * @return array{discount_pct: float, total_discounts: float, total_gross: float}
     */
    public function discountMarginImpact(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('discount-impact', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $query = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to);

            $row = $query
                ->selectRaw('SUM(discount_total + regional_discount_total + bundle_discount_total) as total_discounts')
                ->selectRaw('SUM(subtotal) as total_gross')
                ->first();

            $gross = (float) ($row->total_gross ?? 0);
            $discounts = (float) ($row->total_discounts ?? 0);

            return [
                'discount_pct' => $gross > 0 ? round($discounts / $gross * 100, 1) : 0,
                'total_discounts' => $discounts,
                'total_gross' => $gross,
            ];
        });
    }

    /**
     * @return array{abandoned: int, reminded: int, recovered: int, recovery_pct: float}
     */
    public function cartRecoveryFunnel(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('cart-recovery', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $query = DB::table('cart_abandonments');
            $this->applyPeriod($query, $from, $to, 'abandoned_at');

            $row = $query
                ->selectRaw('COUNT(*) as abandoned')
                ->selectRaw('SUM(reminder_sent_at IS NOT NULL) as reminded')
                ->selectRaw('SUM(recovered_at IS NOT NULL) as recovered')
                ->first();

            $reminded = (int) $row->reminded;
            $recovered = (int) $row->recovered;

            return [
                'abandoned' => (int) $row->abandoned,
                'reminded' => $reminded,
                'recovered' => $recovered,
                'recovery_pct' => $reminded > 0 ? round($recovered / $reminded * 100, 1) : 0,
            ];
        });
    }

    // ── Customers Tab ─────────────────────────────────────────

    /**
     * @return array{total: int, new_in_period: int, total_newsletter: int}
     */
    public function customerStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('customer-stats', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $newQuery = User::where('is_admin', false);
            $this->applyPeriod($newQuery, $from, $to, 'created_at');

            return [
                'total' => User::where('is_admin', false)->count(),
                'new_in_period' => (int) $newQuery->count(),
                'total_newsletter' => DB::table('newsletter_subscribers')
                    ->whereNull('unsubscribed_at')
                    ->count(),
            ];
        });
    }

    /**
     * @return array<int, array{country: string, count: int}>
     */
    public function customerGeography(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('customer-geography', $from, $to);

        return Cache::remember($key, 300, function () use ($limit, $from, $to): array {
            $query = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to);

            return $query
                ->selectRaw('country, COUNT(DISTINCT email) as count')
                ->groupBy('country')
                ->orderByDesc('count')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'country' => $row->country,
                    'count' => (int) $row->count,
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array{name: string, type: string, followers: int}>
     */
    public function tagFollowPopularity(int $limit = 15): array
    {
        return Cache::remember('dashboard:tag-follows', 300, function () use ($limit): array {
            return DB::table('tag_follows')
                ->join('tags', 'tags.id', '=', 'tag_follows.tag_id')
                ->selectRaw('tags.name, tags.type, COUNT(*) as followers')
                ->groupBy('tag_follows.tag_id', 'tags.name', 'tags.type')
                ->orderByDesc('followers')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'type' => $row->type,
                    'followers' => (int) $row->followers,
                ])
                ->all();
        });
    }

    // ── Historical / Seasonal ─────────────────────────────────

    /**
     * Monthly revenue grouped by year for multi-year overlay chart.
     *
     * @return array{years: array<int, array<int, float>>, average: array<int, float>, months: list<string>}
     */
    public function monthlyRevenueByYear(int $yearsBack = 4): array
    {
        $cacheKey = "dashboard:monthly-revenue-by-year:{$yearsBack}";

        return Cache::remember($cacheKey, 300, function () use ($yearsBack): array {
            $currentYear = (int) Carbon::now()->year;
            $startYear = $currentYear - $yearsBack;

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $yearExpr = $isSqlite ? "cast(strftime('%Y', placed_at) as integer)" : 'YEAR(placed_at)';
            $monthExpr = $isSqlite ? "cast(strftime('%m', placed_at) as integer)" : 'MONTH(placed_at)';

            $rows = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->whereRaw("{$yearExpr} >= ?", [$startYear])
                ->selectRaw("{$yearExpr} as yr, {$monthExpr} as mo, SUM(total) as revenue")
                ->groupBy('yr', 'mo')
                ->orderBy('yr')
                ->orderBy('mo')
                ->get();

            $years = [];
            foreach (range($startYear, $currentYear) as $year) {
                $years[$year] = array_fill(1, 12, 0.0);
            }

            foreach ($rows as $row) {
                $years[(int) $row->yr][(int) $row->mo] = (float) $row->revenue;
            }

            // Compute historical average (previous years only)
            $average = array_fill(1, 12, 0.0);
            $pastYears = array_filter(array_keys($years), fn ($y) => $y < $currentYear);
            $pastCount = count($pastYears);

            if ($pastCount > 0) {
                for ($m = 1; $m <= 12; $m++) {
                    $sum = 0;
                    foreach ($pastYears as $y) {
                        $sum += $years[$y][$m];
                    }
                    $average[$m] = round($sum / $pastCount, 2);
                }
            }

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            return [
                'years' => $years,
                'average' => $average,
                'months' => $months,
            ];
        });
    }

    /**
     * Best-in-class month benchmark: find the all-time best version of a given month.
     *
     * @return array{month_name: string, current_year: int, current_revenue: float, best_year: int|null, best_revenue: float, gap_pct: float|null}
     */
    public function bestMonthBenchmark(?int $month = null): array
    {
        $month = $month ?? (int) Carbon::now()->month;
        $currentYear = (int) Carbon::now()->year;
        $cacheKey = "dashboard:best-month-benchmark:{$month}:{$currentYear}";

        return Cache::remember($cacheKey, 300, function () use ($month, $currentYear): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $yearExpr = $isSqlite ? "cast(strftime('%Y', placed_at) as integer)" : 'YEAR(placed_at)';

            $rows = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->whereMonth('placed_at', $month)
                ->selectRaw("{$yearExpr} as yr, SUM(total) as revenue")
                ->groupBy('yr')
                ->orderByDesc('revenue')
                ->get();

            $currentRevenue = 0.0;
            $bestYear = null;
            $bestRevenue = 0.0;

            foreach ($rows as $row) {
                $yr = (int) $row->yr;
                $rev = (float) $row->revenue;

                if ($yr === $currentYear) {
                    $currentRevenue = $rev;
                }

                if ($rev > $bestRevenue) {
                    $bestRevenue = $rev;
                    $bestYear = $yr;
                }
            }

            $gapPct = null;
            if ($bestRevenue > 0 && $bestYear !== $currentYear) {
                $gapPct = round(($currentRevenue - $bestRevenue) / $bestRevenue * 100, 1);
            }

            $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            return [
                'month_name' => $monthNames[$month],
                'current_year' => $currentYear,
                'current_revenue' => $currentRevenue,
                'best_year' => $bestYear,
                'best_revenue' => $bestRevenue,
                'gap_pct' => $gapPct,
            ];
        });
    }

    // ── Contextual Sales & Product Performance ────────────────

    /**
     * Compare the first N days of sales for two products (by published_at).
     *
     * @return array<string, mixed>
     */
    public function releaseBenchmark(int $productA, int $productB, int $days = 30): array
    {
        $cacheKey = "dashboard:release-benchmark:{$productA}:{$productB}:{$days}";

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, 300, function () use ($productA, $productB, $days): array {
            $products = Product::whereIn('id', [$productA, $productB])
                ->select('id', 'name', 'published_at')
                ->get()
                ->keyBy('id');

            $comparison = [];

            foreach ([$productA, $productB] as $pid) {
                $product = $products->get($pid);
                if (! $product || ! $product->published_at) {
                    $comparison[$pid] = [];

                    continue;
                }

                $releaseDate = Carbon::parse($product->published_at)->startOfDay();
                $endDate = $releaseDate->copy()->addDays($days - 1)->endOfDay();

                $isSqlite = DB::connection()->getDriverName() === 'sqlite';

                $dayNumExpr = $isSqlite
                    ? 'cast(julianday(DATE(orders.placed_at)) - julianday(?) as integer)'
                    : 'DATEDIFF(DATE(orders.placed_at), ?)';

                $rows = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereIn('orders.payment_status', self::PAID_STATUSES)
                    ->where('order_items.product_id', $pid)
                    ->whereBetween('orders.placed_at', [$releaseDate, $endDate])
                    ->selectRaw("{$dayNumExpr} as day_num, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue", [$releaseDate->format('Y-m-d')])
                    ->groupByRaw('day_num')
                    ->orderBy('day_num')
                    ->get()
                    ->keyBy('day_num');

                $daily = [];
                $cumUnits = 0;
                $cumRevenue = 0.0;

                for ($d = 0; $d < $days; $d++) {
                    $row = $rows->get($d);
                    $units = (int) ($row->units ?? 0);
                    $revenue = (float) ($row->revenue ?? 0);
                    $cumUnits += $units;
                    $cumRevenue += $revenue;

                    $daily[] = [
                        'day' => $d + 1,
                        'units' => $units,
                        'revenue' => $revenue,
                        'cumulative_units' => $cumUnits,
                        'cumulative_revenue' => round($cumRevenue, 2),
                    ];
                }

                $comparison[$pid] = $daily;
            }

            return [
                'products' => $products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'published_at' => $p->published_at ? Carbon::parse($p->published_at)->format('Y-m-d') : null,
                ])->values()->all(),
                'comparison' => $comparison,
            ];
        });
    }

    /**
     * Products available for release benchmarking (have a published_at date and sales).
     *
     * @return list<array{id: int, name: string, published_at: string}>
     */
    public function benchmarkableProducts(int $limit = 30): array
    {
        /** @var list<array{id: int, name: string, published_at: string}> */
        return Cache::remember('dashboard:benchmarkable-products', 300, function () use ($limit): array {
            return Product::whereNotNull('published_at')
                ->whereHas('orderItems', fn ($q) => $q->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereIn('orders.payment_status', self::PAID_STATUSES))
                ->orderByDesc('published_at')
                ->take($limit)
                ->get(['id', 'name', 'published_at'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'published_at' => Carbon::parse($p->published_at)->format('Y-m-d'),
                ])
                ->all();
        });
    }

    /**
     * Regional revenue trend: quarterly revenue per country to track growth/contraction.
     *
     * @return array{countries: list<string>, quarters: list<string>, series: array<string, list<float>>}
     */
    public function regionalGrowthTrend(int $topCountries = 6, int $quartersBack = 8): array
    {
        $cacheKey = "dashboard:regional-growth:{$topCountries}:{$quartersBack}";

        /** @var array{countries: list<string>, quarters: list<string>, series: array<string, list<float>>} */
        return Cache::remember($cacheKey, 300, function () use ($topCountries, $quartersBack): array {
            // Find top countries by total revenue
            $countries = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->selectRaw('country, SUM(total) as total_rev')
                ->groupBy('country')
                ->orderByDesc('total_rev')
                ->take($topCountries)
                ->pluck('country')
                ->all();

            if (empty($countries)) {
                return ['countries' => [], 'quarters' => [], 'series' => []];
            }

            $now = Carbon::now();
            $startQuarter = $now->copy()->subQuarters($quartersBack - 1)->startOfQuarter();

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $yearExpr = $isSqlite ? "cast(strftime('%Y', placed_at) as integer)" : 'YEAR(placed_at)';
            $quarterExpr = $isSqlite
                ? "cast((cast(strftime('%m', placed_at) as integer) + 2) / 3 as integer)"
                : 'QUARTER(placed_at)';

            $rows = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereIn('country', $countries)
                ->where('placed_at', '>=', $startQuarter)
                ->selectRaw("{$yearExpr} as yr, {$quarterExpr} as qtr, country, SUM(total) as revenue")
                ->groupBy('yr', 'qtr', 'country')
                ->orderBy('yr')
                ->orderBy('qtr')
                ->get();

            // Build quarter labels and per-country series
            $quarters = [];
            $current = $startQuarter->copy();
            while ($current->lte($now)) {
                $quarters[] = 'Q'.$current->quarter.' '.$current->year;
                $current->addQuarter();
            }

            $series = [];
            foreach ($countries as $country) {
                $series[$country] = array_fill(0, count($quarters), 0.0);
            }

            foreach ($rows as $row) {
                $label = 'Q'.$row->qtr.' '.$row->yr;
                $idx = array_search($label, $quarters);
                if ($idx !== false && isset($series[$row->country])) {
                    $series[$row->country][$idx] = (float) $row->revenue;
                }
            }

            return [
                'countries' => $countries,
                'quarters' => $quarters,
                'series' => $series,
            ];
        });
    }

    /**
     * Product sales velocity decay: compare monthly sales velocity from launch.
     *
     * @return list<array{product_id: int, name: string, published_at: string, months: list<array{month: int, units: int, revenue: float, velocity_pct: float|null}>}>
     */
    public function productDecayTracking(int $limit = 8, int $monthsToTrack = 6): array
    {
        $cacheKey = "dashboard:product-decay:{$limit}:{$monthsToTrack}";

        /** @var list<array{product_id: int, name: string, published_at: string, months: list<array{month: int, units: int, revenue: float, velocity_pct: float|null}>}> */
        return Cache::remember($cacheKey, 300, function () use ($limit, $monthsToTrack): array {
            // Get top products that have a published_at date (at least 2 months old)
            $cutoff = Carbon::now()->subMonths(2);
            $products = Product::whereNotNull('published_at')
                ->where('published_at', '<=', $cutoff)
                ->orderByDesc('published_at')
                ->take($limit)
                ->get(['id', 'name', 'published_at']);

            if ($products->isEmpty()) {
                return [];
            }

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $result = [];

            foreach ($products as $product) {
                $releaseDate = Carbon::parse($product->published_at)->startOfDay();
                $monthsData = [];

                for ($m = 0; $m < $monthsToTrack; $m++) {
                    $monthStart = $releaseDate->copy()->addMonths($m);
                    $monthEnd = $monthStart->copy()->addMonth()->subSecond();

                    // Don't query future months
                    if ($monthStart->isFuture()) {
                        break;
                    }

                    $rows = DB::table('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->whereIn('orders.payment_status', self::PAID_STATUSES)
                        ->where('order_items.product_id', $product->id)
                        ->whereBetween('orders.placed_at', [$monthStart, $monthEnd])
                        ->selectRaw('SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
                        ->first();

                    $units = (int) ($rows->units ?? 0);
                    $revenue = (float) ($rows->revenue ?? 0);

                    $monthsData[] = [
                        'month' => $m + 1,
                        'units' => $units,
                        'revenue' => $revenue,
                        'velocity_pct' => null,
                    ];
                }

                // Calculate velocity % relative to month 1
                if (! empty($monthsData) && $monthsData[0]['units'] > 0) {
                    $baselineUnits = $monthsData[0]['units'];
                    for ($i = 0; $i < count($monthsData); $i++) {
                        $monthsData[$i]['velocity_pct'] = round(
                            ($monthsData[$i]['units'] / $baselineUnits) * 100,
                            1
                        );
                    }
                }

                $result[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'published_at' => Carbon::parse($product->published_at)->format('Y-m-d'),
                    'months' => $monthsData,
                ];
            }

            return $result;
        });
    }
}

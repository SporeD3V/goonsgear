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
     * @return array{total_orders: int, pending_orders: int, revenue: float, net_revenue: float, orders_today: int, low_stock: int, out_of_stock: int, stock_alert_waiting: int, total_products: int, active_products: int}
     */
    public function overviewStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('overview', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $paidQuery = $this->applyPeriod(
                Order::whereIn('payment_status', self::PAID_STATUSES), $from, $to
            );
            $totals = (clone $paidQuery)
                ->selectRaw('COALESCE(SUM(total), 0) as gross, COALESCE(SUM(total - shipping_total - tax_total), 0) as net')
                ->first();

            return [
                'total_orders' => (int) $this->applyPeriod(Order::query(), $from, $to)->count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'revenue' => round((float) $totals->gross, 2),
                'net_revenue' => round((float) $totals->net, 2),
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

    /**
     * Site traffic and conversion metrics: visitors, orders, CR%, revenue per visitor.
     *
     * @return array{visitors: int, orders: int, conversion_pct: float, revenue_per_visitor: float, revenue: float}
     */
    public function siteConversionRate(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('site-conversion', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $visitQuery = DB::table('daily_visits');
            if ($from !== null) {
                $visitQuery->where('date', '>=', $from->toDateString());
            }
            if ($to !== null) {
                $visitQuery->where('date', '<=', $to->toDateString());
            }
            $visitors = (int) $visitQuery->sum('visitor_count');

            $orderQuery = Order::whereIn('payment_status', self::PAID_STATUSES);
            $this->applyPeriod($orderQuery, $from, $to);
            $orders = (int) $orderQuery->count();
            $revenue = (float) $orderQuery->sum('total');

            return [
                'visitors' => $visitors,
                'orders' => $orders,
                'conversion_pct' => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0,
                'revenue_per_visitor' => $visitors > 0 ? round($revenue / $visitors, 2) : 0.0,
                'revenue' => round($revenue, 2),
            ];
        });
    }

    /**
     * Total liability from pre-ordered items: cash collected for items not yet shipped.
     *
     * @return array{total_liability: float, order_count: int, item_count: int}
     */
    public function preorderLiability(): array
    {
        return Cache::remember('dashboard:preorder-liability', 300, function (): array {
            $row = DB::table('orders')
                ->where('status', 'pre-ordered')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->selectRaw('COALESCE(SUM(total), 0) as total_liability')
                ->selectRaw('COALESCE(SUM(subtotal), 0) as product_liability')
                ->selectRaw('COALESCE(SUM(shipping_total), 0) as shipping_liability')
                ->selectRaw('COALESCE(SUM(tax_total), 0) as tax_liability')
                ->selectRaw('COUNT(*) as order_count')
                ->first();

            $itemCount = (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', 'pre-ordered')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->sum('order_items.quantity');

            return [
                'total_liability' => round((float) ($row->total_liability ?? 0), 2),
                'product_liability' => round((float) ($row->product_liability ?? 0), 2),
                'shipping_liability' => round((float) ($row->shipping_liability ?? 0), 2),
                'tax_liability' => round((float) ($row->tax_liability ?? 0), 2),
                'order_count' => (int) ($row->order_count ?? 0),
                'item_count' => $itemCount,
            ];
        });
    }

    /**
     * Fulfillment speed: average days from placed_at to shipped_at for completed orders.
     *
     * @return array{avg_days: float, median_days: float, fastest_days: float, slowest_days: float, shipped_count: int}
     */
    public function fulfillmentSpeed(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('fulfillment-speed', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $query = Order::whereNotNull('shipped_at')
                ->whereNotNull('placed_at')
                ->whereIn('status', ['shipped', 'delivered', 'completed']);

            $this->applyPeriod($query, $from, $to);

            $orders = $query->get(['placed_at', 'shipped_at']);

            if ($orders->isEmpty()) {
                return [
                    'avg_days' => 0.0,
                    'median_days' => 0.0,
                    'fastest_days' => 0.0,
                    'slowest_days' => 0.0,
                    'shipped_count' => 0,
                ];
            }

            $daysArr = $orders->map(function ($o) {
                $placed = Carbon::parse($o->placed_at);
                $shipped = Carbon::parse($o->shipped_at);

                return max(0, round($placed->diffInHours($shipped) / 24, 1));
            })->sort()->values();

            $count = $daysArr->count();
            $mid = intdiv($count, 2);
            $median = $count % 2 === 0
                ? round(($daysArr[$mid - 1] + $daysArr[$mid]) / 2, 1)
                : $daysArr[$mid];

            return [
                'avg_days' => round($daysArr->avg(), 1),
                'median_days' => $median,
                'fastest_days' => $daysArr->first(),
                'slowest_days' => $daysArr->last(),
                'shipped_count' => $count,
            ];
        });
    }

    // ── Sales Tab ─────────────────────────────────────────────

    /**
     * @return array<int, array{day: string, revenue: float, net_revenue: float, gross: float, discounts: float, order_count: int}>
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
                ->selectRaw('SUM(total - shipping_total - tax_total) as net_revenue')
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
                        'net_revenue' => (float) ($row->net_revenue ?? 0),
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
                'net_revenue' => (float) $row->net_revenue,
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
     * Average Order Value broken down by country.
     *
     * @return array<int, array{country: string, aov: float, orders: int, revenue: float}>
     */
    public function aovByCountry(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('aov-by-country', $from, $to);

        return Cache::remember($key, 300, function () use ($limit, $from, $to): array {
            $query = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);

            $this->applyPeriod($query, $from, $to);

            return $query
                ->selectRaw('country, ROUND(AVG(total), 2) as aov, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('country')
                ->orderByDesc('orders')
                ->take($limit)
                ->get()
                ->map(fn ($row) => [
                    'country' => $row->country,
                    'aov' => (float) $row->aov,
                    'orders' => (int) $row->orders,
                    'revenue' => round((float) $row->revenue, 2),
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

    // ── Shipping ───────────────────────────────────────────────

    /**
     * Shipping revenue and estimated margins by country.
     * Uses a flat cost assumption (60%) since actual shipping costs are not tracked.
     *
     * @return array{total_collected: float, total_estimated_cost: float, total_margin: float, by_country: list<array{country: string, orders: int, collected: float, avg_collected: float, estimated_cost: float, margin: float, margin_pct: float}>}
     */
    public function shippingMargins(int $limit = 30): array
    {
        return Cache::remember('dashboard:shipping-margins', 300, function () use ($limit): array {
            $rows = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->where('shipping_total', '>', 0)
                ->groupBy('country')
                ->selectRaw('country, COUNT(*) as orders, SUM(shipping_total) as collected, AVG(shipping_total) as avg_collected')
                ->orderByDesc('collected')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                return ['total_collected' => 0.0, 'total_estimated_cost' => 0.0, 'total_margin' => 0.0, 'by_country' => []];
            }

            $costPct = 0.60;

            $byCountry = $rows->map(fn ($r) => [
                'country' => $r->country ?: 'Unknown',
                'orders' => (int) $r->orders,
                'collected' => round((float) $r->collected, 2),
                'avg_collected' => round((float) $r->avg_collected, 2),
                'estimated_cost' => round((float) $r->collected * $costPct, 2),
                'margin' => round((float) $r->collected * (1 - $costPct), 2),
                'margin_pct' => round((1 - $costPct) * 100, 1),
            ])->all();

            $totalCollected = (float) $rows->sum('collected');

            return [
                'total_collected' => round($totalCollected, 2),
                'total_estimated_cost' => round($totalCollected * $costPct, 2),
                'total_margin' => round($totalCollected * (1 - $costPct), 2),
                'by_country' => $byCountry,
            ];
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
     * Days of stock remaining for low-stock variants based on 30-day sales velocity.
     *
     * @return array<int, array{product: string, variant: string, sku: string, stock: int, daily_velocity: float, days_remaining: float|null}>
     */
    public function daysOfStockRemaining(int $limit = 20): array
    {
        return Cache::remember('dashboard:days-of-stock', 300, function () use ($limit): array {
            // Get variants with stock between 1 and 20 (critical + low)
            $variants = DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where('pv.is_active', true)
                ->where('pv.stock_quantity', '>', 0)
                ->where('pv.stock_quantity', '<=', 20)
                ->select('pv.id', 'pv.sku', 'pv.name as variant', 'p.name as product', 'pv.stock_quantity as stock')
                ->get();

            if ($variants->isEmpty()) {
                return [];
            }

            // Smart velocity: get sales across multiple windows in one query
            $now = Carbon::now();
            $thirtyDaysAgo = $now->copy()->subDays(30);
            $ninetyDaysAgo = $now->copy()->subDays(90);
            $yearAgo = $now->copy()->subDays(365);

            $sales = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->whereIn('order_items.product_variant_id', $variants->pluck('id'))
                ->groupBy('order_items.product_variant_id')
                ->selectRaw('order_items.product_variant_id, SUM(order_items.quantity) as total_sold, SUM(CASE WHEN orders.placed_at >= ? THEN order_items.quantity ELSE 0 END) as sold_30d, SUM(CASE WHEN orders.placed_at >= ? THEN order_items.quantity ELSE 0 END) as sold_90d, SUM(CASE WHEN orders.placed_at >= ? THEN order_items.quantity ELSE 0 END) as sold_365d, MIN(orders.placed_at) as first_sale_at', [$thirtyDaysAgo, $ninetyDaysAgo, $yearAgo])
                ->get()
                ->keyBy('product_variant_id');

            return $variants->map(function ($v) use ($sales, $now) {
                $sale = $sales->get($v->id);

                if (! $sale) {
                    return [
                        'product' => $v->product,
                        'variant' => $v->variant,
                        'sku' => $v->sku,
                        'stock' => (int) $v->stock,
                        'daily_velocity' => 0.0,
                        'days_remaining' => null,
                        'velocity_window' => null,
                    ];
                }

                $sold30 = (int) ($sale->sold_30d ?? 0);
                $sold90 = (int) ($sale->sold_90d ?? 0);
                $sold365 = (int) ($sale->sold_365d ?? 0);
                $soldAll = (int) ($sale->total_sold ?? 0);

                // Cascade: 30d → 90d → 365d → all-time
                if ($sold30 > 0) {
                    $dailyVelocity = $sold30 / 30;
                    $velocityWindow = '30d';
                } elseif ($sold90 > 0) {
                    $dailyVelocity = $sold90 / 90;
                    $velocityWindow = '90d';
                } elseif ($sold365 > 0) {
                    $dailyVelocity = $sold365 / 365;
                    $velocityWindow = '365d';
                } elseif ($soldAll > 0) {
                    $firstSale = Carbon::parse($sale->first_sale_at);
                    $daysActive = max(1, (int) $firstSale->diffInDays($now));
                    $dailyVelocity = $soldAll / $daysActive;
                    $velocityWindow = 'all';
                } else {
                    $dailyVelocity = 0;
                    $velocityWindow = null;
                }

                return [
                    'product' => $v->product,
                    'variant' => $v->variant,
                    'sku' => $v->sku,
                    'stock' => (int) $v->stock,
                    'daily_velocity' => round($dailyVelocity, 2),
                    'days_remaining' => $dailyVelocity > 0 ? round($v->stock / $dailyVelocity, 1) : null,
                    'velocity_window' => $velocityWindow,
                ];
            })
                ->sortBy('days_remaining')
                ->take($limit)
                ->values()
                ->all();
        });
    }

    /**
     * Revenue at risk from sold-out variants: estimated monthly revenue loss based on historical sales velocity.
     *
     * @return array{total_monthly_revenue: float, variant_count: int, product_count: int, top_items: list<array{product: string, variant: string, sku: string, monthly_revenue: float, avg_daily_units: float, avg_price: float}>}
     */
    public function revenueAtRisk(int $topLimit = 20): array
    {
        return Cache::remember('dashboard:revenue-at-risk', 300, function () use ($topLimit): array {
            // Get all active variants that are sold out (stock = 0)
            $soldOut = DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where('pv.is_active', true)
                ->where('pv.stock_quantity', 0)
                ->select('pv.id', 'pv.sku', 'pv.name as variant', 'p.name as product', 'p.id as product_id', 'p.published_at')
                ->get();

            if ($soldOut->isEmpty()) {
                return [
                    'total_monthly_revenue' => 0.0,
                    'variant_count' => 0,
                    'product_count' => 0,
                    'top_items' => [],
                ];
            }

            // Calculate 90-day historical sales velocity per variant.
            // Use last sale date as proxy for when the item went OOS,
            // so we only divide by days the item was actually in stock.
            $ninetyDaysAgo = Carbon::now()->subDays(90);
            $sales = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->where('orders.placed_at', '>=', $ninetyDaysAgo)
                ->whereIn('order_items.product_variant_id', $soldOut->pluck('id'))
                ->groupBy('order_items.product_variant_id')
                ->selectRaw('order_items.product_variant_id, SUM(order_items.quantity) as total_sold, SUM(order_items.line_total) as total_revenue, MAX(orders.placed_at) as last_sale_at')
                ->get()
                ->keyBy('product_variant_id');

            $items = $soldOut->map(function ($v) use ($sales, $ninetyDaysAgo) {
                $sale = $sales->get($v->id);
                if (! $sale || (int) $sale->total_sold === 0) {
                    return null;
                }

                $totalSold = (int) $sale->total_sold;
                $totalRevenue = (float) $sale->total_revenue;

                // Days actually in stock = MAX(published_at, windowStart) to last sale
                $lastSale = Carbon::parse($sale->last_sale_at);
                $windowStart = $ninetyDaysAgo;
                if ($v->published_at) {
                    $publishDate = Carbon::parse($v->published_at);
                    if ($publishDate->gt($ninetyDaysAgo)) {
                        $windowStart = $publishDate;
                    }
                }
                $daysInStock = max(1, (int) $windowStart->diffInDays($lastSale));

                $avgDailyUnits = round($totalSold / $daysInStock, 2);
                $avgPrice = round($totalRevenue / $totalSold, 2);
                $monthlyRevenue = round($avgDailyUnits * 30 * $avgPrice, 2);

                return [
                    'product' => $v->product,
                    'variant' => $v->variant,
                    'sku' => $v->sku,
                    'monthly_revenue' => $monthlyRevenue,
                    'avg_daily_units' => $avgDailyUnits,
                    'avg_price' => $avgPrice,
                    'days_in_stock' => $daysInStock,
                ];
            })
                ->filter()
                ->sortByDesc('monthly_revenue')
                ->values();

            return [
                'total_monthly_revenue' => round($items->sum('monthly_revenue'), 2),
                'variant_count' => $soldOut->count(),
                'product_count' => $soldOut->pluck('product_id')->unique()->count(),
                'top_items' => $items->take($topLimit)->all(),
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
     * Dead stock: active variants with stock > 10 that haven't sold in 180+ days (or never).
     * Suggests "Clearance Sale" for items with no recent affinity, "Bundle Inclusion" for items that pair well.
     *
     * @return array{total_units: int, total_value: float, items: list<array{product: string, variant: string, sku: string, stock: int, unit_price: float, stock_value: float, days_since_last_sale: int|null, total_ever_sold: int, suggestion: string}>}
     */
    public function deadStock(int $limit = 50): array
    {
        return Cache::remember('dashboard:dead-stock', 300, function () use ($limit): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $cutoff = Carbon::now()->subDays(180);

            // Variants with stock > 10 that are active
            $variants = DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where('pv.is_active', true)
                ->where('pv.stock_quantity', '>', 10)
                ->select('pv.id', 'pv.sku', 'pv.name as variant', 'p.name as product', 'pv.stock_quantity as stock', 'pv.price')
                ->get();

            if ($variants->isEmpty()) {
                return ['total_units' => 0, 'total_value' => 0.0, 'items' => []];
            }

            // Get last sale date and total sold per variant
            $salesData = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->whereNotNull('orders.placed_at')
                ->whereIn('order_items.product_variant_id', $variants->pluck('id'))
                ->groupBy('order_items.product_variant_id')
                ->selectRaw('order_items.product_variant_id, MAX(orders.placed_at) as last_sale_at, SUM(order_items.quantity) as total_sold')
                ->get()
                ->keyBy('product_variant_id');

            // Get co-purchase counts to determine bundle suitability
            $coProductCounts = DB::table('order_items as oi1')
                ->join('order_items as oi2', function ($join) {
                    $join->on('oi1.order_id', '=', 'oi2.order_id')
                        ->whereColumn('oi1.product_variant_id', '!=', 'oi2.product_variant_id');
                })
                ->join('orders', 'orders.id', '=', 'oi1.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->whereIn('oi1.product_variant_id', $variants->pluck('id'))
                ->groupBy('oi1.product_variant_id')
                ->selectRaw('oi1.product_variant_id, COUNT(DISTINCT oi1.order_id) as co_orders')
                ->pluck('co_orders', 'product_variant_id');

            $items = $variants->map(function ($v) use ($salesData, $coProductCounts, $cutoff) {
                $sale = $salesData->get($v->id);
                $lastSaleAt = $sale ? Carbon::parse($sale->last_sale_at) : null;
                $totalSold = $sale ? (int) $sale->total_sold : 0;

                // Skip if last sale is within 180 days
                if ($lastSaleAt && $lastSaleAt->gte($cutoff)) {
                    return null;
                }

                $daysSinceLastSale = $lastSaleAt ? (int) $lastSaleAt->diffInDays(Carbon::now()) : null;
                $coOrders = (int) ($coProductCounts[$v->id] ?? 0);
                $unitPrice = (float) $v->price;

                // Suggestion logic: if it's been bought with other products before → bundle candidate
                $suggestion = $coOrders >= 3 ? 'Bundle Inclusion' : 'Clearance Sale';

                return [
                    'product' => $v->product,
                    'variant' => $v->variant,
                    'sku' => $v->sku,
                    'stock' => (int) $v->stock,
                    'unit_price' => $unitPrice,
                    'stock_value' => round($unitPrice * (int) $v->stock, 2),
                    'days_since_last_sale' => $daysSinceLastSale,
                    'total_ever_sold' => $totalSold,
                    'suggestion' => $suggestion,
                ];
            })
                ->filter()
                ->sortByDesc('stock_value')
                ->values();

            return [
                'total_units' => $items->sum('stock'),
                'total_value' => round($items->sum('stock_value'), 2),
                'items' => $items->take($limit)->all(),
            ];
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

    /**
     * Top Abandoned Products: most-abandoned products from cart_abandonments JSON.
     *
     * @return list<array{product_id: int, product_name: string, times_abandoned: int, total_qty: int, avg_price: float}>
     */
    public function topAbandonedProducts(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('top-abandoned-products', $from, $to);

        /** @var list<array{product_id: int, product_name: string, times_abandoned: int, total_qty: int, avg_price: float}> */
        return Cache::remember($key, 300, function () use ($limit, $from, $to): array {
            $query = DB::table('cart_abandonments')
                ->whereNull('recovered_at');

            $this->applyPeriod($query, $from, $to, 'abandoned_at');

            $abandonments = $query->select('cart_data')->get();

            if ($abandonments->isEmpty()) {
                return [];
            }

            $products = [];
            foreach ($abandonments as $row) {
                $items = is_string($row->cart_data) ? json_decode($row->cart_data, true) : $row->cart_data;
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    if (! isset($item['product_id'])) {
                        continue;
                    }
                    $pid = (int) $item['product_id'];
                    if (! isset($products[$pid])) {
                        $products[$pid] = [
                            'product_id' => $pid,
                            'product_name' => $item['product_name'] ?? 'Unknown',
                            'times_abandoned' => 0,
                            'total_qty' => 0,
                            'total_price' => 0.0,
                        ];
                    }
                    $products[$pid]['times_abandoned']++;
                    $products[$pid]['total_qty'] += (int) ($item['quantity'] ?? 1);
                    $products[$pid]['total_price'] += (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
                }
            }

            usort($products, fn ($a, $b) => $b['times_abandoned'] <=> $a['times_abandoned']);
            $products = array_slice($products, 0, $limit);

            return array_map(fn ($p) => [
                'product_id' => $p['product_id'],
                'product_name' => $p['product_name'],
                'times_abandoned' => $p['times_abandoned'],
                'total_qty' => $p['total_qty'],
                'avg_price' => $p['total_qty'] > 0 ? round($p['total_price'] / $p['total_qty'], 2) : 0.0,
            ], $products);
        });
    }

    // ── Customers Tab ─────────────────────────────────────────

    /**
     * @return array{total: int, active_in_period: int, new_in_period: int, total_newsletter: int}
     */
    public function customerStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $key = $this->periodCacheKey('customer-stats', $from, $to);

        return Cache::remember($key, 300, function () use ($from, $to): array {
            $newQuery = User::where('is_admin', false);
            $this->applyPeriod($newQuery, $from, $to, 'created_at');

            $activeQuery = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES);
            $this->applyPeriod($activeQuery, $from, $to);

            return [
                'total' => User::where('is_admin', false)->count(),
                'active_in_period' => (int) $activeQuery->distinct()->count('email'),
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

    // ── Advanced Customer Context ─────────────────────────────

    /**
     * Cohort retention: for each acquisition year, what % of customers returned within 12 months.
     *
     * @return list<array{year: int, total_customers: int, retained: int, retention_pct: float, is_complete: bool}>
     */
    public function cohortRetentionHistory(): array
    {
        /** @var list<array{year: int, total_customers: int, retained: int, retention_pct: float, is_complete: bool}> */
        return Cache::remember('dashboard:cohort-retention', 300, function (): array {
            // Step 1: Get first order date per email
            $firstOrders = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->selectRaw('email, MIN(placed_at) as first_placed_at')
                ->groupBy('email')
                ->get();

            if ($firstOrders->isEmpty()) {
                return [];
            }

            // Step 2: Get all orders grouped by email for return checking
            $allOrders = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->select('email', 'placed_at')
                ->orderBy('placed_at')
                ->get()
                ->groupBy('email');

            // Step 3: Process cohorts in PHP
            $cohorts = [];
            $now = Carbon::now();

            foreach ($firstOrders as $row) {
                $firstDate = Carbon::parse($row->first_placed_at);
                $year = $firstDate->year;

                if (! isset($cohorts[$year])) {
                    $cohorts[$year] = ['total' => 0, 'retained' => 0];
                }
                $cohorts[$year]['total']++;

                // Check for return order within 12 months of first order
                $cutoff = $firstDate->copy()->addYear();
                $customerOrders = $allOrders->get($row->email, collect());
                $hasReturn = $customerOrders->contains(function ($order) use ($firstDate, $cutoff) {
                    $orderDate = Carbon::parse($order->placed_at);

                    return $orderDate->gt($firstDate) && $orderDate->lte($cutoff);
                });

                if ($hasReturn) {
                    $cohorts[$year]['retained']++;
                }
            }

            // Build sorted result
            ksort($cohorts);
            $result = [];

            foreach ($cohorts as $year => $data) {
                // A cohort is "complete" if 12 months have passed since year end
                $yearEnd = Carbon::createFromDate($year, 12, 31)->endOfDay();

                $result[] = [
                    'year' => $year,
                    'total_customers' => $data['total'],
                    'retained' => $data['retained'],
                    'retention_pct' => round($data['retained'] / $data['total'] * 100, 1),
                    'is_complete' => $yearEnd->copy()->addYear()->lte($now),
                ];
            }

            return $result;
        });
    }

    /**
     * AOV breakdown by year: decompose into avg items/order × avg price/item.
     *
     * @return list<array{year: int, total_orders: int, aov: float, avg_items_per_order: float, avg_price_per_item: float}>
     */
    public function aovBreakdown(int $yearsBack = 4): array
    {
        $cacheKey = "dashboard:aov-breakdown:{$yearsBack}";

        /** @var list<array{year: int, total_orders: int, aov: float, avg_items_per_order: float, avg_price_per_item: float}> */
        return Cache::remember($cacheKey, 300, function () use ($yearsBack): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $yearExpr = $isSqlite
                ? "cast(strftime('%Y', orders.placed_at) as integer)"
                : 'YEAR(orders.placed_at)';

            $startYear = (int) Carbon::now()->year - $yearsBack + 1;

            $rows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->whereNotNull('orders.placed_at')
                ->where('orders.placed_at', '>=', Carbon::createFromDate($startYear, 1, 1))
                ->selectRaw("{$yearExpr} as yr")
                ->selectRaw('COUNT(DISTINCT orders.id) as total_orders')
                ->selectRaw('SUM(order_items.quantity) as total_items')
                ->selectRaw('SUM(order_items.line_total) as total_revenue')
                ->groupBy('yr')
                ->orderBy('yr')
                ->get();

            return $rows->map(function ($row): array {
                $totalOrders = (int) $row->total_orders;
                $totalItems = (int) $row->total_items;
                $totalRevenue = (float) $row->total_revenue;

                return [
                    'year' => (int) $row->yr,
                    'total_orders' => $totalOrders,
                    'aov' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
                    'avg_items_per_order' => $totalOrders > 0 ? round($totalItems / $totalOrders, 1) : 0,
                    'avg_price_per_item' => $totalItems > 0 ? round($totalRevenue / $totalItems, 2) : 0,
                ];
            })->all();
        });
    }

    /**
     * Waitlist conversion benchmark: back-in-stock alert conversion for new products vs restocks.
     * New = subscription created within 90 days of product publish. Restock = older products.
     *
     * @return array{first_release: array{notified: int, converted: int, conversion_pct: float}, restock: array{notified: int, converted: int, conversion_pct: float}}
     */
    public function waitlistConversionBenchmark(): array
    {
        /** @var array{first_release: array{notified: int, converted: int, conversion_pct: float}, restock: array{notified: int, converted: int, conversion_pct: float}} */
        return Cache::remember('dashboard:waitlist-conversion', 300, function (): array {
            $empty = ['notified' => 0, 'converted' => 0, 'conversion_pct' => 0.0];

            // Step 1: Get all notified subscriptions with product/email info
            $subscriptions = DB::table('stock_alert_subscriptions as sas')
                ->join('product_variants as pv', 'pv.id', '=', 'sas.product_variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->leftJoin('users as u', 'u.id', '=', 'sas.user_id')
                ->whereNotNull('sas.notified_at')
                ->select([
                    'sas.id',
                    'sas.notified_at',
                    'sas.created_at as sub_created_at',
                    'pv.product_id',
                    'p.published_at',
                    DB::raw('COALESCE(sas.email, u.email) as subscriber_email'),
                ])
                ->get();

            if ($subscriptions->isEmpty()) {
                return ['first_release' => $empty, 'restock' => $empty];
            }

            // Step 2: Get all potential conversion orders (matching emails + products)
            $emails = $subscriptions->pluck('subscriber_email')->filter()->unique()->values()->all();
            $productIds = $subscriptions->pluck('product_id')->unique()->values()->all();

            $conversionOrders = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.payment_status', self::PAID_STATUSES)
                ->whereIn('orders.email', $emails)
                ->whereIn('order_items.product_id', $productIds)
                ->select('orders.email', 'order_items.product_id', 'orders.placed_at')
                ->get();

            // Step 3: Match subscriptions to conversions in PHP
            $buckets = [
                'first_release' => ['notified' => 0, 'converted' => 0],
                'restock' => ['notified' => 0, 'converted' => 0],
            ];

            foreach ($subscriptions as $sub) {
                if (! $sub->subscriber_email) {
                    continue;
                }

                // Classify: new product (<= 90 days from publish) vs restock
                $isNew = false;
                if ($sub->published_at) {
                    $daysSincePublish = Carbon::parse($sub->published_at)
                        ->diffInDays(Carbon::parse($sub->sub_created_at));
                    $isNew = $daysSincePublish <= 90;
                }

                $key = $isNew ? 'first_release' : 'restock';
                $buckets[$key]['notified']++;

                // Check if subscriber bought this product after notification
                $notifiedAt = Carbon::parse($sub->notified_at);
                $converted = $conversionOrders->contains(function ($order) use ($sub, $notifiedAt) {
                    return $order->email === $sub->subscriber_email
                        && (int) $order->product_id === (int) $sub->product_id
                        && Carbon::parse($order->placed_at)->gte($notifiedAt);
                });

                if ($converted) {
                    $buckets[$key]['converted']++;
                }
            }

            return [
                'first_release' => [
                    'notified' => $buckets['first_release']['notified'],
                    'converted' => $buckets['first_release']['converted'],
                    'conversion_pct' => $buckets['first_release']['notified'] > 0
                        ? round($buckets['first_release']['converted'] / $buckets['first_release']['notified'] * 100, 1)
                        : 0.0,
                ],
                'restock' => [
                    'notified' => $buckets['restock']['notified'],
                    'converted' => $buckets['restock']['converted'],
                    'conversion_pct' => $buckets['restock']['notified'] > 0
                        ? round($buckets['restock']['converted'] / $buckets['restock']['notified'] * 100, 1)
                        : 0.0,
                ],
            ];
        });
    }

    /**
     * RFM segmentation: group customers by Recency, Frequency, Monetary scores.
     *
     * @return array{segments: array<string, array{count: int, avg_revenue: float, avg_orders: float, color: string}>, customers_analyzed: int}
     */
    public function rfmSegmentation(): array
    {
        /** @var array{segments: array<string, array{count: int, avg_revenue: float, avg_orders: float, color: string}>, customers_analyzed: int} */
        return Cache::remember('dashboard:rfm-segmentation', 300, function (): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';

            $daysSinceExpr = $isSqlite
                ? 'cast(julianday(?) - julianday(MAX(placed_at)) as integer)'
                : 'DATEDIFF(?, MAX(placed_at))';

            $today = Carbon::today()->format('Y-m-d');

            // Get per-customer RFM raw values
            $customers = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->groupBy('email')
                ->selectRaw("email, {$daysSinceExpr} as days_since_last, COUNT(*) as order_count, SUM(total) as total_spent", [$today])
                ->get();

            if ($customers->isEmpty()) {
                return ['segments' => [], 'customers_analyzed' => 0];
            }

            // Score each customer 1-5 using quintiles
            $dayValues = $customers->pluck('days_since_last')->sort()->values();
            $freqValues = $customers->pluck('order_count')->sort()->values();
            $monValues = $customers->pluck('total_spent')->sort()->values();

            $quintile = function (Collection $sorted, float $value, bool $inverse = false): int {
                $count = $sorted->count();
                if ($count === 0) {
                    return 3;
                }
                $position = $sorted->search(function ($v) use ($value) {
                    return $v >= $value;
                });
                if ($position === false) {
                    $position = $count;
                }
                $pct = $position / $count;
                $score = match (true) {
                    $pct <= 0.2 => 1,
                    $pct <= 0.4 => 2,
                    $pct <= 0.6 => 3,
                    $pct <= 0.8 => 4,
                    default => 5,
                };

                return $inverse ? (6 - $score) : $score;
            };

            $segmentDefs = [
                'Champions' => ['color' => '#4bc0c0', 'min_r' => 4, 'max_r' => 5, 'min_f' => 4, 'max_f' => 5, 'min_m' => 4, 'max_m' => 5],
                'Loyal' => ['color' => '#36a2eb', 'min_r' => 3, 'max_r' => 5, 'min_f' => 3, 'max_f' => 5, 'min_m' => 3, 'max_m' => 5],
                'At Risk' => ['color' => '#ff6384', 'min_r' => 0, 'max_r' => 2, 'min_f' => 3, 'max_f' => 5, 'min_m' => 3, 'max_m' => 5],
                'New' => ['color' => '#ff9f40', 'min_r' => 4, 'max_r' => 5, 'min_f' => 0, 'max_f' => 2, 'min_m' => 0, 'max_m' => 5],
                'Low Value' => ['color' => '#c9cbcf', 'min_r' => 0, 'max_r' => 5, 'min_f' => 0, 'max_f' => 5, 'min_m' => 0, 'max_m' => 5],
            ];

            $segments = [];
            foreach (array_keys($segmentDefs) as $name) {
                $segments[$name] = ['count' => 0, 'total_revenue' => 0.0, 'total_orders' => 0];
            }

            foreach ($customers as $c) {
                $r = $quintile($dayValues, (float) $c->days_since_last, true);
                $f = $quintile($freqValues, (float) $c->order_count);
                $m = $quintile($monValues, (float) $c->total_spent);

                $assigned = 'Low Value';
                foreach ($segmentDefs as $name => $def) {
                    if ($name === 'Low Value') {
                        continue;
                    }
                    $rOk = $r >= $def['min_r'] && $r <= $def['max_r'];
                    $fOk = $f >= $def['min_f'] && $f <= $def['max_f'];
                    $mOk = $m >= $def['min_m'] && $m <= $def['max_m']; // @phpstan-ignore smallerOrEqual.alwaysTrue
                    if ($rOk && $fOk && $mOk) {
                        $assigned = $name;
                        break;
                    }
                }

                $segments[$assigned]['count']++;
                $segments[$assigned]['total_revenue'] += (float) $c->total_spent;
                $segments[$assigned]['total_orders'] += (int) $c->order_count;
            }

            $result = [];
            foreach ($segments as $name => $data) {
                $result[$name] = [
                    'count' => $data['count'],
                    'avg_revenue' => $data['count'] > 0 ? round($data['total_revenue'] / $data['count'], 2) : 0.0,
                    'avg_orders' => $data['count'] > 0 ? round($data['total_orders'] / $data['count'], 1) : 0.0,
                    'color' => $segmentDefs[$name]['color'],
                ];
            }

            return [
                'segments' => $result,
                'customers_analyzed' => $customers->count(),
            ];
        });
    }

    /**
     * Customer Lifetime Value: avg total spend per customer, with trend by acquisition year.
     *
     * @return array{overall_clv: float, total_customers: int, total_revenue: float, by_year: list<array{year: int, customers: int, clv: float, avg_orders: float}>}
     */
    public function customerLifetimeValue(): array
    {
        /** @var array{overall_clv: float, total_customers: int, total_revenue: float, by_year: list<array{year: int, customers: int, clv: float, avg_orders: float}>} */
        return Cache::remember('dashboard:clv', 300, function (): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';

            // Per-customer aggregates
            $customers = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->groupBy('email')
                ->selectRaw('email, MIN(placed_at) as first_order, COUNT(*) as order_count, SUM(total) as total_spent')
                ->get();

            if ($customers->isEmpty()) {
                return ['overall_clv' => 0.0, 'total_customers' => 0, 'total_revenue' => 0.0, 'by_year' => []];
            }

            $totalCustomers = $customers->count();
            $totalRevenue = (float) $customers->sum('total_spent');
            $overallClv = round($totalRevenue / $totalCustomers, 2);

            // Group by acquisition year
            $yearBuckets = [];
            foreach ($customers as $c) {
                $year = Carbon::parse($c->first_order)->year;
                if (! isset($yearBuckets[$year])) {
                    $yearBuckets[$year] = ['customers' => 0, 'total_spent' => 0.0, 'total_orders' => 0];
                }
                $yearBuckets[$year]['customers']++;
                $yearBuckets[$year]['total_spent'] += (float) $c->total_spent;
                $yearBuckets[$year]['total_orders'] += (int) $c->order_count;
            }

            ksort($yearBuckets);
            $byYear = [];
            foreach ($yearBuckets as $year => $data) {
                $byYear[] = [
                    'year' => $year,
                    'customers' => $data['customers'],
                    'clv' => round($data['total_spent'] / $data['customers'], 2),
                    'avg_orders' => round($data['total_orders'] / $data['customers'], 1),
                ];
            }

            return [
                'overall_clv' => $overallClv,
                'total_customers' => $totalCustomers,
                'total_revenue' => round($totalRevenue, 2),
                'by_year' => $byYear,
            ];
        });
    }

    /**
     * VIP Churn Warning: top 5% spenders who haven't ordered within their
     * personalised churn window (2.5× their average purchase interval, floored
     * at 90 days and capped at 365).
     *
     * @return array{vip_threshold: float, vip_total: int, at_risk_vips: list<array{email: string, total_spent: float, order_count: int, days_since_last: int, churn_threshold: int, last_order: string}>, lost_vips: list<array{email: string, total_spent: float, order_count: int, days_since_last: int, churn_threshold: int, last_order: string}>}
     */
    public function vipChurnWarning(): array
    {
        /** @var array{vip_threshold: float, vip_total: int, at_risk_vips: list<array{email: string, total_spent: float, order_count: int, days_since_last: int, churn_threshold: int, last_order: string}>, lost_vips: list<array{email: string, total_spent: float, order_count: int, days_since_last: int, churn_threshold: int, last_order: string}>} */
        return Cache::remember('dashboard:vip-churn', 300, function (): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';

            $daysSinceExpr = $isSqlite
                ? 'CAST(julianday("now") - julianday(MAX(placed_at)) AS INTEGER)'
                : 'DATEDIFF(NOW(), MAX(placed_at))';

            $spanExpr = $isSqlite
                ? 'CAST(julianday(MAX(placed_at)) - julianday(MIN(placed_at)) AS INTEGER)'
                : 'DATEDIFF(MAX(placed_at), MIN(placed_at))';

            $customers = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->groupBy('email')
                ->selectRaw("email, COUNT(*) as order_count, SUM(total) as total_spent, MAX(placed_at) as last_order, {$daysSinceExpr} as days_since_last, {$spanExpr} as order_span_days")
                ->get();

            if ($customers->isEmpty()) {
                return ['vip_threshold' => 0.0, 'vip_total' => 0, 'at_risk_vips' => [], 'lost_vips' => []];
            }

            // Sort by total_spent descending to find top 5%
            $sorted = $customers->sortByDesc('total_spent')->values();
            $vipCount = max(1, (int) ceil($sorted->count() * 0.05));
            $threshold = (float) $sorted[$vipCount - 1]->total_spent;

            $vips = $sorted->take($vipCount);
            $churning = $vips->filter(function ($c) {
                $churnThreshold = $this->calculateChurnThreshold(
                    (int) $c->order_count,
                    (int) ($c->order_span_days ?? 0)
                );

                return (int) $c->days_since_last >= $churnThreshold;
            })
                ->map(function ($c) {
                    $churnThreshold = $this->calculateChurnThreshold(
                        (int) $c->order_count,
                        (int) ($c->order_span_days ?? 0)
                    );

                    return [
                        'email' => $c->email,
                        'total_spent' => round((float) $c->total_spent, 2),
                        'order_count' => (int) $c->order_count,
                        'days_since_last' => (int) $c->days_since_last,
                        'churn_threshold' => $churnThreshold,
                        'last_order' => Carbon::parse($c->last_order)->format('M j, Y'),
                    ];
                })
                ->sortByDesc('days_since_last')
                ->values();

            // Split: at-risk (churn threshold to 365d) vs lost (365d+)
            $atRisk = $churning->filter(fn ($c) => $c['days_since_last'] < 365)->values()->all();
            $lost = $churning->filter(fn ($c) => $c['days_since_last'] >= 365)->values()->all();

            return [
                'vip_threshold' => round($threshold, 2),
                'vip_total' => $vipCount,
                'at_risk_vips' => $atRisk,
                'lost_vips' => $lost,
            ];
        });
    }

    /**
     * Dynamic churn threshold: 2.5× average purchase interval, floored at 90, capped at 365.
     */
    private function calculateChurnThreshold(int $orderCount, int $orderSpanDays): int
    {
        if ($orderCount <= 1) {
            return 90;
        }

        $avgInterval = $orderSpanDays / ($orderCount - 1);

        return (int) max(90, min($avgInterval * 2.5, 365));
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
     * Includes MTD (month-to-date) comparison so mid-month stats compare fairly.
     *
     * @return array{month_name: string, current_year: int, current_revenue: float, best_year: int|null, best_revenue: float, gap_pct: float|null, mtd_day: int, mtd_current: float, mtd_best_revenue: float, mtd_best_year: int|null, mtd_gap_pct: float|null}
     */
    public function bestMonthBenchmark(?int $month = null): array
    {
        $month = $month ?? (int) Carbon::now()->month;
        $currentYear = (int) Carbon::now()->year;
        $currentDay = (int) Carbon::now()->day;
        $cacheKey = "dashboard:best-month-benchmark:{$month}:{$currentYear}:{$currentDay}";

        return Cache::remember($cacheKey, 300, function () use ($month, $currentYear, $currentDay): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $yearExpr = $isSqlite ? "cast(strftime('%Y', placed_at) as integer)" : 'YEAR(placed_at)';
            $dayExpr = $isSqlite ? "cast(strftime('%d', placed_at) as integer)" : 'DAY(placed_at)';

            // Full month totals per year
            $rows = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->whereMonth('placed_at', $month)
                ->selectRaw("{$yearExpr} as yr, SUM(total) as revenue")
                ->groupBy('yr')
                ->orderByDesc('revenue')
                ->get();

            // MTD totals per year (same day range: 1st to current day)
            $mtdRows = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->whereMonth('placed_at', $month)
                ->whereRaw("{$dayExpr} <= ?", [$currentDay])
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

            // MTD: compare same day range across years
            $mtdCurrent = 0.0;
            $mtdBestRevenue = 0.0;
            $mtdBestYear = null;

            foreach ($mtdRows as $row) {
                $yr = (int) $row->yr;
                $rev = (float) $row->revenue;

                if ($yr === $currentYear) {
                    $mtdCurrent = $rev;
                }

                if ($rev > $mtdBestRevenue && $yr !== $currentYear) {
                    $mtdBestRevenue = $rev;
                    $mtdBestYear = $yr;
                }
            }

            $mtdGapPct = null;
            if ($mtdBestRevenue > 0) {
                $mtdGapPct = round(($mtdCurrent - $mtdBestRevenue) / $mtdBestRevenue * 100, 1);
            }

            $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            return [
                'month_name' => $monthNames[$month],
                'current_year' => $currentYear,
                'current_revenue' => $currentRevenue,
                'best_year' => $bestYear,
                'best_revenue' => $bestRevenue,
                'gap_pct' => $gapPct,
                'mtd_day' => $currentDay,
                'mtd_current' => $mtdCurrent,
                'mtd_best_revenue' => $mtdBestRevenue,
                'mtd_best_year' => $mtdBestYear,
                'mtd_gap_pct' => $mtdGapPct,
            ];
        });
    }

    // ── Contextual Sales & Product Performance ────────────────

    /**
     * Compare the first N days of sales for two products.
     * Optionally accepts custom start dates to override published_at.
     *
     * @return array<string, mixed>
     */
    public function releaseBenchmark(int $productA, int $productB, int $days = 30, ?string $startA = null, ?string $startB = null): array
    {
        $startAKey = $startA ?: 'default';
        $startBKey = $startB ?: 'default';
        $cacheKey = "dashboard:release-benchmark:{$productA}:{$productB}:{$days}:{$startAKey}:{$startBKey}";

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, 300, function () use ($productA, $productB, $days, $startA, $startB): array {
            $products = Product::whereIn('id', [$productA, $productB])
                ->select('id', 'name', 'published_at')
                ->get()
                ->keyBy('id');

            $customStarts = [$productA => $startA, $productB => $startB];
            $comparison = [];

            foreach ([$productA, $productB] as $pid) {
                $product = $products->get($pid);
                if (! $product) {
                    $comparison[$pid] = [];

                    continue;
                }

                // Use custom start date if provided, otherwise fall back to published_at
                $customStart = $customStarts[$pid] ?? null;
                $releaseDateRaw = $customStart ?: $product->published_at;
                if (! $releaseDateRaw) {
                    $comparison[$pid] = [];

                    continue;
                }

                $releaseDate = Carbon::parse($releaseDateRaw)->startOfDay();
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
                    'custom_start' => isset($customStarts[$p->id]) && $customStarts[$p->id] ? $customStarts[$p->id] : null,
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
     * Includes AOV per country per quarter for overlay analysis.
     *
     * @return array{countries: list<string>, quarters: list<string>, series: array<string, list<float>>, aov_series: array<string, list<float>>}
     */
    public function regionalGrowthTrend(int $topCountries = 6, int $quartersBack = 8): array
    {
        $cacheKey = "dashboard:regional-growth:{$topCountries}:{$quartersBack}";

        /** @var array{countries: list<string>, quarters: list<string>, series: array<string, list<float>>, aov_series: array<string, list<float>>} */
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
                return ['countries' => [], 'quarters' => [], 'series' => [], 'aov_series' => []];
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
                ->selectRaw("{$yearExpr} as yr, {$quarterExpr} as qtr, country, SUM(total) as revenue, COUNT(*) as order_count")
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
            $aovSeries = [];
            foreach ($countries as $country) {
                $series[$country] = array_fill(0, count($quarters), 0.0);
                $aovSeries[$country] = array_fill(0, count($quarters), 0.0);
            }

            foreach ($rows as $row) {
                $label = 'Q'.$row->qtr.' '.$row->yr;
                $idx = array_search($label, $quarters);
                if ($idx !== false && isset($series[$row->country])) {
                    $series[$row->country][$idx] = (float) $row->revenue;
                    $aovSeries[$row->country][$idx] = (int) $row->order_count > 0
                        ? round((float) $row->revenue / (int) $row->order_count, 2)
                        : 0.0;
                }
            }

            return [
                'countries' => $countries,
                'quarters' => $quarters,
                'series' => $series,
                'aov_series' => $aovSeries,
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

    // ── Deep Product & Cart Insights ──────────────────────────

    /**
     * First-Purchase Heroes: which products customers buy as their first order.
     *
     * @return list<array{product_name: string, first_purchases: int, pct: float, avg_ltv: float}>
     */
    public function firstPurchaseHeroes(int $limit = 10): array
    {
        /** @var list<array{product_name: string, first_purchases: int, pct: float, avg_ltv: float}> */
        return Cache::remember('dashboard:first-purchase-heroes', 300, function () use ($limit): array {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $minExpr = $isSqlite
                ? 'MIN(julianday(o.placed_at))'
                : 'MIN(o.placed_at)';

            // Subquery: find each customer's first order
            $firstOrders = DB::table('orders as o')
                ->whereIn('o.payment_status', self::PAID_STATUSES)
                ->whereNotNull('o.placed_at')
                ->groupBy('o.email')
                ->selectRaw("o.email, MIN(o.id) as first_order_id, {$minExpr} as first_placed");

            // Join to get items from those first orders
            $rows = DB::table('order_items as oi')
                ->joinSub($firstOrders, 'fo', 'oi.order_id', '=', 'fo.first_order_id')
                ->selectRaw('oi.product_name, COUNT(*) as first_purchases')
                ->groupBy('oi.product_name')
                ->orderByDesc('first_purchases')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $total = $rows->sum('first_purchases');

            // Get emails per hero product for LTV calculation
            $heroNames = $rows->pluck('product_name');
            $emailsByProduct = DB::table('order_items as oi')
                ->joinSub($firstOrders, 'fo', 'oi.order_id', '=', 'fo.first_order_id')
                ->whereIn('oi.product_name', $heroNames)
                ->select('oi.product_name', 'fo.email')
                ->distinct()
                ->get()
                ->groupBy('product_name');

            // Lifetime spend per customer
            $allEmails = $emailsByProduct->flatten()->pluck('email')->unique();
            $ltvByEmail = DB::table('orders')
                ->whereIn('payment_status', self::PAID_STATUSES)
                ->whereNotNull('placed_at')
                ->whereIn('email', $allEmails)
                ->groupBy('email')
                ->selectRaw('email, SUM(total) as lifetime_spend')
                ->pluck('lifetime_spend', 'email');

            return $rows->map(function ($r) use ($total, $emailsByProduct, $ltvByEmail) {
                $emails = $emailsByProduct->get($r->product_name, collect());
                $ltvSum = 0;
                $ltvCount = 0;
                foreach ($emails as $item) {
                    if (isset($ltvByEmail[$item->email])) {
                        $ltvSum += (float) $ltvByEmail[$item->email];
                        $ltvCount++;
                    }
                }

                return [
                    'product_name' => $r->product_name,
                    'first_purchases' => (int) $r->first_purchases,
                    'pct' => $total > 0 ? round(((int) $r->first_purchases / $total) * 100, 1) : 0.0,
                    'avg_ltv' => $ltvCount > 0 ? round($ltvSum / $ltvCount, 2) : 0.0,
                ];
            })->all();
        });
    }

    /**
     * Product Affinity (Market Basket): products frequently bought together.
     * Includes lift score: lift = P(A∩B) / (P(A) × P(B)). Lift > 1 means genuine affinity.
     *
     * @return list<array{product_a: string, product_b: string, co_purchases: int, affinity_pct: float, lift: float}>
     */
    public function productAffinity(int $limit = 10): array
    {
        /** @var list<array{product_a: string, product_b: string, co_purchases: int, affinity_pct: float, lift: float}> */
        return Cache::remember('dashboard:product-affinity', 300, function () use ($limit): array {
            // Get all paid orders that have 2+ distinct products
            $orderProducts = DB::table('order_items as oi')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->whereIn('o.payment_status', self::PAID_STATUSES)
                ->whereNotNull('o.placed_at')
                ->select('oi.order_id', 'oi.product_name')
                ->distinct()
                ->get()
                ->groupBy('order_id');

            if ($orderProducts->isEmpty()) {
                return [];
            }

            // Count product purchase totals (for affinity %)
            $productCounts = [];
            $pairs = [];

            foreach ($orderProducts as $orderId => $items) {
                $names = $items->pluck('product_name')->unique()->sort()->values()->all();

                foreach ($names as $name) {
                    $productCounts[$name] = ($productCounts[$name] ?? 0) + 1;
                }

                // Generate all pairs (sorted to avoid A→B and B→A duplicates)
                $count = count($names);
                if ($count < 2) {
                    continue;
                }

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $key = $names[$i].'|||'.$names[$j];
                        $pairs[$key] = ($pairs[$key] ?? 0) + 1;
                    }
                }
            }

            if (empty($pairs)) {
                return [];
            }

            // Sort by co-purchase count
            arsort($pairs);
            $topPairs = array_slice($pairs, 0, $limit, true);

            $totalOrders = $orderProducts->count();
            $result = [];
            foreach ($topPairs as $key => $coPurchases) {
                [$a, $b] = explode('|||', $key);
                // Affinity % = co-purchases / purchases of less popular product
                $minCount = min($productCounts[$a] ?? 1, $productCounts[$b] ?? 1);
                // Lift = P(A∩B) / (P(A) × P(B))
                $pA = ($productCounts[$a] ?? 1) / max(1, $totalOrders);
                $pB = ($productCounts[$b] ?? 1) / max(1, $totalOrders);
                $pAB = $coPurchases / max(1, $totalOrders);
                $lift = ($pA * $pB) > 0 ? round($pAB / ($pA * $pB), 2) : 0.0;

                $result[] = [
                    'product_a' => $a,
                    'product_b' => $b,
                    'co_purchases' => $coPurchases,
                    'affinity_pct' => round(($coPurchases / $minCount) * 100, 1),
                    'lift' => $lift,
                ];
            }

            return $result;
        });
    }
}

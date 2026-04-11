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
                ->groupBy('order_coupon_usages.coupon_code')
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
}

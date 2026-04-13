<?php

defined('ABSPATH') || exit;

/**
 * Bulk sync: push all WooCommerce data since a given date.
 *
 * Used to close the gap between the legacy DB export and when the
 * real-time webhook hooks go live.
 *
 * Processed via AJAX in small batches to avoid timeouts.
 */
class GG_Sync_Bulk_Sync
{
    private const BATCH_SIZE = 25;

    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        add_action('wp_ajax_gg_sync_bulk_sync', [$this, 'ajax_process_batch']);
        add_action('wp_ajax_gg_sync_bulk_count', [$this, 'ajax_count']);
    }

    /* ------------------------------------------------------------------
     *  AJAX: count items to sync
     * ----------------------------------------------------------------*/

    public function ajax_count(): void
    {
        check_ajax_referer('gg_sync_bulk');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $since  = $this->parse_since();
        $domain = sanitize_text_field($_POST['domain'] ?? 'all');

        $counts = [];

        if ($domain === 'all' || $domain === 'order') {
            $counts['order'] = $this->count_orders($since);
        }
        if ($domain === 'all' || $domain === 'product') {
            $counts['product'] = $this->count_products($since);
        }
        if ($domain === 'all' || $domain === 'coupon') {
            $counts['coupon'] = $this->count_coupons($since);
        }
        if ($domain === 'all' || $domain === 'customer') {
            $counts['customer'] = $this->count_customers($since);
        }

        wp_send_json_success([
            'counts' => $counts,
            'total'  => array_sum($counts),
        ]);
    }

    /* ------------------------------------------------------------------
     *  AJAX: process one batch
     * ----------------------------------------------------------------*/

    public function ajax_process_batch(): void
    {
        check_ajax_referer('gg_sync_bulk');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $since  = $this->parse_since();
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $offset = absint($_POST['offset'] ?? 0);

        if (! in_array($domain, ['order', 'product', 'coupon', 'customer'], true)) {
            wp_send_json_error(['message' => 'Invalid domain.']);
        }

        $method  = "sync_{$domain}_batch";
        $sent    = $this->$method($since, $offset);

        wp_send_json_success([
            'domain'    => $domain,
            'offset'    => $offset,
            'sent'      => $sent,
            'batch_size' => self::BATCH_SIZE,
        ]);
    }

    /* ------------------------------------------------------------------
     *  Counting
     * ----------------------------------------------------------------*/

    private function count_orders(string $since): int
    {
        global $wpdb;

        // WC stores orders either as wp_posts (legacy) or in wp_wc_orders (HPOS).
        if ($this->has_hpos()) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE date_created_gmt >= %s AND type = 'shop_order'",
                $since
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_date_gmt >= %s AND post_status NOT IN ('auto-draft', 'trash')",
            $since
        ));
    }

    private function count_products(string $since): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_modified_gmt >= %s AND post_status NOT IN ('auto-draft', 'trash')",
            $since
        ));
    }

    private function count_coupons(string $since): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_modified_gmt >= %s AND post_status = 'publish'",
            $since
        ));
    }

    private function count_customers(string $since): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s",
            $since
        ));
    }

    /* ------------------------------------------------------------------
     *  Batch processing
     * ----------------------------------------------------------------*/

    private function sync_order_batch(string $since, int $offset): int
    {
        $args = [
            'date_created' => '>=' . $since,
            'limit'        => self::BATCH_SIZE,
            'offset'       => $offset,
            'orderby'      => 'date',
            'order'        => 'ASC',
            'status'       => array_keys(wc_get_order_statuses()),
        ];

        $orders = wc_get_orders($args);
        $order_sync = new GG_Sync_Order_Sync($this->dispatcher);
        $sent = 0;

        foreach ($orders as $order) {
            $order_sync->on_new_order($order->get_id());
            $sent++;
        }

        return $sent;
    }

    private function sync_product_batch(string $since, int $offset): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_modified_gmt >= %s AND post_status NOT IN ('auto-draft', 'trash') ORDER BY ID ASC LIMIT %d OFFSET %d",
            $since,
            self::BATCH_SIZE,
            $offset
        ));

        $product_sync = new GG_Sync_Product_Sync($this->dispatcher);
        $sent = 0;

        foreach ($product_ids as $product_id) {
            $product_sync->on_updated((int) $product_id);
            $sent++;
        }

        return $sent;
    }

    private function sync_coupon_batch(string $since, int $offset): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $coupon_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_modified_gmt >= %s AND post_status = 'publish' ORDER BY ID ASC LIMIT %d OFFSET %d",
            $since,
            self::BATCH_SIZE,
            $offset
        ));

        $sent = 0;

        foreach ($coupon_ids as $coupon_id) {
            $coupon = new \WC_Coupon((int) $coupon_id);
            $this->dispatcher->send(
                'coupon.saved',
                [
                    'wc_coupon_id'         => $coupon->get_id(),
                    'code'                 => $coupon->get_code(),
                    'discount_type'        => $coupon->get_discount_type(),
                    'amount'               => $coupon->get_amount(),
                    'usage_count'          => $coupon->get_usage_count(),
                    'usage_limit'          => $coupon->get_usage_limit(),
                    'usage_limit_per_user' => $coupon->get_usage_limit_per_user(),
                    'date_expires'         => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('c') : null,
                    'minimum_amount'       => $coupon->get_minimum_amount(),
                    'maximum_amount'       => $coupon->get_maximum_amount(),
                    'free_shipping'        => $coupon->get_free_shipping(),
                    'individual_use'       => $coupon->get_individual_use(),
                    'exclude_sale_items'   => $coupon->get_exclude_sale_items(),
                    'description'          => $coupon->get_description(),
                ],
                "bulk.coupon.{$coupon->get_id()}"
            );
            $sent++;
        }

        return $sent;
    }

    private function sync_customer_batch(string $since, int $offset): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_registered >= %s ORDER BY ID ASC LIMIT %d OFFSET %d",
            $since,
            self::BATCH_SIZE,
            $offset
        ));

        $customer_sync = new GG_Sync_Customer_Sync($this->dispatcher);
        $sent = 0;

        foreach ($user_ids as $user_id) {
            $customer_sync->on_created((int) $user_id);
            $sent++;
        }

        return $sent;
    }

    /* ------------------------------------------------------------------
     *  Helpers
     * ----------------------------------------------------------------*/

    private function parse_since(): string
    {
        $since = sanitize_text_field($_POST['since'] ?? '');

        // Validate YYYY-MM-DD format.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            wp_send_json_error(['message' => 'Invalid date format. Use YYYY-MM-DD.']);
        }

        return $since . ' 00:00:00';
    }

    private function has_hpos(): bool
    {
        return class_exists('Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
}

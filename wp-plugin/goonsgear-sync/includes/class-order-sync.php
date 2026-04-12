<?php

defined('ABSPATH') || exit;

/**
 * Hooks into WooCommerce order lifecycle events and pushes data to GoonGear.
 *
 * Events fired:
 *   order.created        — New order placed
 *   order.status_changed — Status transition (e.g. processing → completed)
 *   order.refunded       — Refund processed on an order
 */
class GG_Sync_Order_Sync
{
    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        add_action('woocommerce_new_order', [$this, 'on_new_order'], 20, 1);
        add_action('woocommerce_order_status_changed', [$this, 'on_status_changed'], 20, 4);
        add_action('woocommerce_order_refunded', [$this, 'on_refunded'], 20, 2);
    }

    public function on_new_order(int $order_id): void
    {
        $order = wc_get_order($order_id);

        if (! $order) {
            return;
        }

        $this->dispatcher->send(
            'order.created',
            $this->build_payload($order),
            "order.created.{$order_id}"
        );
    }

    /**
     * @param  string   $old_status  Without 'wc-' prefix.
     * @param  string   $new_status  Without 'wc-' prefix.
     * @param  \WC_Order $order
     */
    public function on_status_changed(int $order_id, string $old_status, string $new_status, $order): void
    {
        $data = $this->build_payload($order);
        $data['old_status'] = $old_status;
        $data['new_status'] = $new_status;

        $this->dispatcher->send(
            'order.status_changed',
            $data,
            "order.status.{$order_id}.{$new_status}"
        );
    }

    public function on_refunded(int $order_id, int $refund_id): void
    {
        $order  = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (! $order || ! $refund) {
            return;
        }

        $data = $this->build_payload($order);
        $data['refund'] = [
            'wc_refund_id' => $refund_id,
            'amount'       => $refund->get_amount(),
            'reason'       => $refund->get_reason(),
        ];

        $this->dispatcher->send(
            'order.refunded',
            $data,
            "order.refunded.{$order_id}.{$refund_id}"
        );
    }

    /* ------------------------------------------------------------------
     *  Payload builder
     * ----------------------------------------------------------------*/

    /**
     * Build the full order payload matching GG's Order model fields.
     *
     * @return array<string, mixed>
     */
    private function build_payload(\WC_Order $order): array
    {
        return [
            'wc_order_id'    => $order->get_id(),
            'order_number'   => $order->get_order_number(),
            'status'         => $order->get_status(),
            'payment_method' => $order->get_payment_method(),
            'currency'       => $order->get_currency(),

            // Billing / customer.
            'email'      => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'phone'      => $order->get_billing_phone(),

            // Shipping address.
            'shipping' => [
                'country'     => $order->get_shipping_country(),
                'state'       => $order->get_shipping_state(),
                'city'        => $order->get_shipping_city(),
                'postal_code' => $order->get_shipping_postcode(),
                'address_1'   => $order->get_shipping_address_1(),
                'address_2'   => $order->get_shipping_address_2(),
            ],

            // Totals.
            'subtotal'       => $order->get_subtotal(),
            'total'          => $order->get_total(),
            'shipping_total' => $order->get_shipping_total(),
            'tax_total'      => $order->get_total_tax(),
            'discount_total' => $order->get_total_discount(),
            'refund_total'   => (float) $order->get_total_refunded(),

            // Coupons.
            'coupon_codes' => $this->get_coupon_codes($order),

            // Dates.
            'placed_at'  => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
            'paid_at'    => $order->get_date_paid() ? $order->get_date_paid()->format('c') : null,

            // Tracking (from WC Shipment Tracking plugin).
            'tracking' => $this->get_tracking_data($order->get_id()),

            // Line items.
            'items' => $this->get_items($order),
        ];
    }

    /**
     * @return list<string>
     */
    private function get_coupon_codes(\WC_Order $order): array
    {
        return array_values($order->get_coupon_codes());
    }

    /**
     * @return list<array{wc_product_id: int, wc_variation_id: int, name: string, sku: string, quantity: int, unit_price: float, line_total: float}>
     */
    private function get_items(\WC_Order $order): array
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $product   = $item->get_product();
            $items[]   = [
                'wc_product_id'   => $item->get_product_id(),
                'wc_variation_id' => $item->get_variation_id(),
                'name'            => $item->get_name(),
                'sku'             => $product ? $product->get_sku() : '',
                'quantity'        => $item->get_quantity(),
                'unit_price'      => (float) ($item->get_subtotal() / max($item->get_quantity(), 1)),
                'line_total'      => (float) $item->get_total(),
            ];
        }

        return $items;
    }

    /**
     * Extract tracking data from the WC Shipment Tracking plugin meta.
     *
     * @return array{carrier: string, tracking_number: string, shipped_at: string|null}|null
     */
    private function get_tracking_data(int $order_id): ?array
    {
        $raw = get_post_meta($order_id, '_wc_shipment_tracking_items', true);

        if (empty($raw) || ! is_array($raw)) {
            return null;
        }

        $entry = reset($raw);

        if (! is_array($entry)) {
            return null;
        }

        return [
            'carrier'         => $entry['tracking_provider'] ?? $entry['custom_tracking_provider'] ?? '',
            'tracking_number' => $entry['tracking_number'] ?? '',
            'shipped_at'      => ! empty($entry['date_shipped'])
                ? gmdate('c', (int) $entry['date_shipped'])
                : null,
        ];
    }
}

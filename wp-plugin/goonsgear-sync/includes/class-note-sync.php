<?php

defined('ABSPATH') || exit;

/**
 * Hooks into order note and shipment tracking events.
 *
 * Events fired:
 *   note.created     — Admin note added to an order
 *   note.tracking    — Tracking info added/updated on an order
 */
class GG_Sync_Note_Sync
{
    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        // WooCommerce order notes are stored as comments of type 'order_note'.
        add_action('woocommerce_order_note_added', [$this, 'on_note_added'], 20, 2);

        // WC Shipment Tracking plugin hook.
        add_action('added_post_meta', [$this, 'on_tracking_meta_added'], 20, 4);
        add_action('updated_post_meta', [$this, 'on_tracking_meta_updated'], 20, 4);
    }

    /**
     * Fires when a note is added via WC's order notes API.
     *
     * @param  int       $note_id  The comment ID.
     * @param  \WC_Order $order
     */
    public function on_note_added(int $note_id, $order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }

        $comment = get_comment($note_id);

        if (! $comment) {
            return;
        }

        $is_customer_note = get_comment_meta($note_id, 'is_customer_note', true) === '1';

        $this->dispatcher->send(
            'note.created',
            [
                'wc_order_id'      => $order->get_id(),
                'wc_note_id'       => $note_id,
                'content'          => $comment->comment_content,
                'is_customer_note' => $is_customer_note,
                'author'           => $comment->comment_author,
                'created_at'       => $comment->comment_date_gmt,
            ],
            "note.created.{$note_id}"
        );
    }

    /**
     * Listen for tracking meta being added to an order.
     *
     * @param  int    $meta_id
     * @param  int    $object_id  Post (order) ID.
     * @param  string $meta_key
     * @param  mixed  $meta_value
     */
    public function on_tracking_meta_added(int $meta_id, int $object_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== '_wc_shipment_tracking_items') {
            return;
        }

        $this->send_tracking_event($object_id, $meta_value);
    }

    /**
     * Listen for tracking meta being updated on an order.
     *
     * @param  int    $meta_id
     * @param  int    $object_id
     * @param  string $meta_key
     * @param  mixed  $meta_value
     */
    public function on_tracking_meta_updated(int $meta_id, int $object_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== '_wc_shipment_tracking_items') {
            return;
        }

        $this->send_tracking_event($object_id, $meta_value);
    }

    /* ------------------------------------------------------------------
     *  Internal
     * ----------------------------------------------------------------*/

    /**
     * @param  int   $order_id
     * @param  mixed $tracking_items Raw serialized or array tracking data.
     */
    private function send_tracking_event(int $order_id, $tracking_items): void
    {
        if (get_post_type($order_id) !== 'shop_order') {
            return;
        }

        if (is_string($tracking_items)) {
            $tracking_items = maybe_unserialize($tracking_items);
        }

        if (! is_array($tracking_items) || empty($tracking_items)) {
            return;
        }

        $entry = reset($tracking_items);

        if (! is_array($entry)) {
            return;
        }

        $this->dispatcher->send(
            'note.tracking',
            [
                'wc_order_id'     => $order_id,
                'carrier'         => $entry['tracking_provider'] ?? $entry['custom_tracking_provider'] ?? '',
                'tracking_number' => $entry['tracking_number'] ?? '',
                'shipped_at'      => ! empty($entry['date_shipped'])
                    ? gmdate('c', (int) $entry['date_shipped'])
                    : null,
            ],
            "note.tracking.{$order_id}"
        );
    }
}

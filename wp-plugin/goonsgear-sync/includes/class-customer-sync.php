<?php

defined('ABSPATH') || exit;

/**
 * Hooks into WordPress/WooCommerce customer events and pushes data to GoonGear.
 *
 * Events fired:
 *   customer.created — New customer registered
 *   customer.updated — Profile data changed
 */
class GG_Sync_Customer_Sync
{
    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        add_action('woocommerce_created_customer', [$this, 'on_created'], 20, 1);
        add_action('profile_update', [$this, 'on_updated'], 20, 2);
    }

    public function on_created(int $user_id): void
    {
        $this->dispatcher->send(
            'customer.created',
            $this->build_payload($user_id),
            "customer.created.{$user_id}"
        );
    }

    /**
     * @param  int $user_id
     * @param  \WP_User|null $old_user_data
     */
    public function on_updated(int $user_id, $old_user_data = null): void
    {
        // Only sync users who have placed orders (customers).
        $order_count = wc_get_customer_order_count($user_id);

        if ($order_count < 1) {
            return;
        }

        $this->dispatcher->send(
            'customer.updated',
            $this->build_payload($user_id),
            "customer.updated.{$user_id}"
        );
    }

    /* ------------------------------------------------------------------
     *  Payload builder
     * ----------------------------------------------------------------*/

    /**
     * @return array<string, mixed>
     */
    private function build_payload(int $user_id): array
    {
        $customer = new \WC_Customer($user_id);

        return [
            'wc_user_id'  => $user_id,
            'email'       => $customer->get_email(),
            'first_name'  => $customer->get_first_name(),
            'last_name'   => $customer->get_last_name(),
            'order_count' => $customer->get_order_count(),

            'billing' => [
                'phone'       => $customer->get_billing_phone(),
                'country'     => $customer->get_billing_country(),
                'state'       => $customer->get_billing_state(),
                'city'        => $customer->get_billing_city(),
                'postal_code' => $customer->get_billing_postcode(),
                'address_1'   => $customer->get_billing_address_1(),
                'address_2'   => $customer->get_billing_address_2(),
            ],

            'shipping' => [
                'country'     => $customer->get_shipping_country(),
                'state'       => $customer->get_shipping_state(),
                'city'        => $customer->get_shipping_city(),
                'postal_code' => $customer->get_shipping_postcode(),
                'address_1'   => $customer->get_shipping_address_1(),
                'address_2'   => $customer->get_shipping_address_2(),
            ],
        ];
    }
}

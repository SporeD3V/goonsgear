<?php

defined('ABSPATH') || exit;

/**
 * Hooks into WooCommerce coupon events and pushes data to GoonGear.
 *
 * Events fired:
 *   coupon.saved   — Coupon created or updated
 *   coupon.deleted — Coupon permanently deleted
 */
class GG_Sync_Coupon_Sync
{
    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        add_action('woocommerce_coupon_options_save', [$this, 'on_saved'], 20, 2);
        add_action('before_delete_post', [$this, 'on_deleted'], 20, 1);
    }

    /**
     * @param  int $coupon_id
     * @param  \WC_Coupon $coupon
     */
    public function on_saved(int $coupon_id, $coupon): void
    {
        if (! $coupon instanceof \WC_Coupon) {
            $coupon = new \WC_Coupon($coupon_id);
        }

        $this->dispatcher->send(
            'coupon.saved',
            $this->build_payload($coupon),
            "coupon.saved.{$coupon_id}"
        );
    }

    public function on_deleted(int $post_id): void
    {
        if (get_post_type($post_id) !== 'shop_coupon') {
            return;
        }

        $this->dispatcher->send(
            'coupon.deleted',
            ['wc_coupon_id' => $post_id],
            "coupon.deleted.{$post_id}"
        );
    }

    /* ------------------------------------------------------------------
     *  Payload builder
     * ----------------------------------------------------------------*/

    /**
     * @return array<string, mixed>
     */
    private function build_payload(\WC_Coupon $coupon): array
    {
        return [
            'wc_coupon_id'      => $coupon->get_id(),
            'code'              => $coupon->get_code(),
            'discount_type'     => $coupon->get_discount_type(),
            'amount'            => $coupon->get_amount(),
            'usage_count'       => $coupon->get_usage_count(),
            'usage_limit'       => $coupon->get_usage_limit(),
            'usage_limit_per_user' => $coupon->get_usage_limit_per_user(),
            'date_expires'      => $coupon->get_date_expires() ? $coupon->get_date_expires()->format('c') : null,
            'minimum_amount'    => $coupon->get_minimum_amount(),
            'maximum_amount'    => $coupon->get_maximum_amount(),
            'free_shipping'     => $coupon->get_free_shipping(),
            'individual_use'    => $coupon->get_individual_use(),
            'exclude_sale_items' => $coupon->get_exclude_sale_items(),
            'description'       => $coupon->get_description(),
        ];
    }
}

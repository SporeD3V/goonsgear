<?php

defined('ABSPATH') || exit;

/**
 * Hooks into WooCommerce product lifecycle events and pushes data to GoonGear.
 *
 * Events fired:
 *   product.created  — New product published
 *   product.updated  — Product data changed (includes stock changes)
 *   product.trashed  — Product moved to trash
 *   product.restored — Product restored from trash
 */
class GG_Sync_Product_Sync
{
    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        add_action('woocommerce_new_product', [$this, 'on_created'], 20, 1);
        add_action('woocommerce_update_product', [$this, 'on_updated'], 20, 2);
        add_action('wp_trash_post', [$this, 'on_trashed'], 20, 1);
        add_action('untrashed_post', [$this, 'on_restored'], 20, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'on_variation_stock'], 20, 1);
    }

    public function on_created(int $product_id): void
    {
        $product = wc_get_product($product_id);

        if (! $product) {
            return;
        }

        $this->dispatcher->send(
            'product.created',
            $this->build_payload($product),
            "product.created.{$product_id}"
        );
    }

    /**
     * @param  \WC_Product|int $product  WC passes the object in newer versions.
     */
    public function on_updated(int $product_id, $product = null): void
    {
        if (! $product instanceof \WC_Product) {
            $product = wc_get_product($product_id);
        }

        if (! $product || $product->get_status() === 'auto-draft') {
            return;
        }

        $this->dispatcher->send(
            'product.updated',
            $this->build_payload($product),
            "product.updated.{$product_id}"
        );
    }

    public function on_trashed(int $post_id): void
    {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $this->dispatcher->send(
            'product.trashed',
            ['wc_product_id' => $post_id],
            "product.trashed.{$post_id}"
        );
    }

    public function on_restored(int $post_id): void
    {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $product = wc_get_product($post_id);

        if (! $product) {
            return;
        }

        $this->dispatcher->send(
            'product.restored',
            $this->build_payload($product),
            "product.restored.{$post_id}"
        );
    }

    /**
     * Fires when a variation's stock changes (e.g. order placed or manual edit).
     */
    public function on_variation_stock(\WC_Product_Variation $variation): void
    {
        $parent_id = $variation->get_parent_id();

        $this->dispatcher->send(
            'product.stock_changed',
            [
                'wc_product_id'   => $parent_id,
                'wc_variation_id' => $variation->get_id(),
                'sku'             => $variation->get_sku(),
                'stock_quantity'  => $variation->get_stock_quantity(),
                'stock_status'    => $variation->get_stock_status(),
            ],
            "product.stock.{$variation->get_id()}"
        );
    }

    /* ------------------------------------------------------------------
     *  Payload builder
     * ----------------------------------------------------------------*/

    /**
     * Build the full product payload matching GG's Product model fields.
     *
     * @return array<string, mixed>
     */
    private function build_payload(\WC_Product $product): array
    {
        return [
            'wc_product_id' => $product->get_id(),
            'name'          => $product->get_name(),
            'slug'          => $product->get_slug(),
            'status'        => $product->get_status(),
            'type'          => $product->get_type(),

            'excerpt'     => $product->get_short_description(),
            'description' => $product->get_description(),

            'is_featured'  => $product->is_featured(),
            'is_preorder'  => (bool) get_post_meta($product->get_id(), '_is_pre_order', true),

            // Dimensions & weight.
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width'  => $product->get_width(),
            'height' => $product->get_height(),

            // Pricing (for simple products).
            'price'         => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price'    => $product->get_sale_price(),

            // Stock.
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status'   => $product->get_stock_status(),

            // Dates.
            'published_at' => $product->get_date_created() ? $product->get_date_created()->format('c') : null,

            // Taxonomy.
            'categories' => $this->get_category_terms($product),
            'tags'       => $this->get_tag_terms($product),

            // Variations (for variable products).
            'variants' => $this->get_variants($product),

            // Images.
            'images' => $this->get_images($product),
        ];
    }

    /**
     * @return list<array{wc_term_id: int, name: string, slug: string}>
     */
    private function get_category_terms(\WC_Product $product): array
    {
        $terms  = [];
        $raw    = wp_get_post_terms($product->get_id(), 'product_cat');

        if (is_wp_error($raw)) {
            return [];
        }

        foreach ($raw as $term) {
            $terms[] = [
                'wc_term_id' => $term->term_id,
                'name'       => $term->name,
                'slug'       => $term->slug,
            ];
        }

        return $terms;
    }

    /**
     * @return list<array{wc_term_id: int, name: string, slug: string}>
     */
    private function get_tag_terms(\WC_Product $product): array
    {
        $terms = [];
        $raw   = wp_get_post_terms($product->get_id(), 'product_tag');

        if (is_wp_error($raw)) {
            return [];
        }

        foreach ($raw as $term) {
            $terms[] = [
                'wc_term_id' => $term->term_id,
                'name'       => $term->name,
                'slug'       => $term->slug,
            ];
        }

        return $terms;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function get_variants(\WC_Product $product): array
    {
        if (! $product->is_type('variable')) {
            return [];
        }

        $variants = [];

        /** @var \WC_Product_Variable $product */
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);

            if (! $variation instanceof \WC_Product_Variation) {
                continue;
            }

            $variants[] = [
                'wc_variation_id' => $variation->get_id(),
                'sku'             => $variation->get_sku(),
                'name'            => implode(' / ', $variation->get_attributes()),
                'price'           => $variation->get_price(),
                'regular_price'   => $variation->get_regular_price(),
                'sale_price'      => $variation->get_sale_price(),
                'stock_quantity'  => $variation->get_stock_quantity(),
                'stock_status'    => $variation->get_stock_status(),
                'weight'          => $variation->get_weight(),
                'is_preorder'     => (bool) get_post_meta($variation->get_id(), '_is_pre_order', true),
            ];
        }

        return $variants;
    }

    /**
     * @return list<array{url: string, alt: string, position: int}>
     */
    private function get_images(\WC_Product $product): array
    {
        $images = [];

        // Featured image.
        $thumb_id = $product->get_image_id();
        if ($thumb_id) {
            $images[] = [
                'url'      => wp_get_attachment_url($thumb_id),
                'alt'      => get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: '',
                'position' => 0,
            ];
        }

        // Gallery images.
        $position = 1;
        foreach ($product->get_gallery_image_ids() as $img_id) {
            $images[] = [
                'url'      => wp_get_attachment_url($img_id),
                'alt'      => get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: '',
                'position' => $position++,
            ];
        }

        return $images;
    }
}

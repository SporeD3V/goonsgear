<?php

defined('ABSPATH') || exit;

/**
 * Authenticated REST endpoints for pulling product images from WP.
 *
 * Endpoints:
 *   POST /wp-json/gg-sync/v1/images/manifest  — List all product images
 *   POST /wp-json/gg-sync/v1/images/download   — Serve a single attachment file
 *
 * All endpoints are HMAC-authenticated using the same shared secret as webhooks.
 */
class GG_Sync_Image_Endpoint
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('gg-sync/v1', '/images/manifest', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_manifest'],
            'permission_callback' => [$this, 'verify_signature'],
        ]);

        register_rest_route('gg-sync/v1', '/images/download', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_download'],
            'permission_callback' => [$this, 'verify_signature'],
        ]);
    }

    /**
     * Verify the HMAC-SHA256 signature on inbound requests.
     */
    public function verify_signature(\WP_REST_Request $request): bool
    {
        $secret    = get_option('gg_sync_webhook_secret', '');
        $signature = $request->get_header('X-GG-Signature');
        $body      = $request->get_body();

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Return a manifest of all product images grouped by WC product ID.
     *
     * Request body (JSON):
     *   { "since": "2026-03-30" }        — optional, only products modified since this date
     *   { "product_ids": [123, 456] }     — optional, only specific products
     *
     * Response:
     *   { "products": [ { "wc_product_id": 123, "images": [...] } ] }
     */
    public function handle_manifest(\WP_REST_Request $request): \WP_REST_Response
    {
        $params      = $request->get_json_params();
        $since       = $params['since'] ?? null;
        $product_ids = $params['product_ids'] ?? null;

        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if ($since && preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            $args['date_query'] = [
                ['after' => $since, 'inclusive' => true, 'column' => 'post_modified_gmt'],
            ];
        }

        if (is_array($product_ids) && ! empty($product_ids)) {
            $args['post__in'] = array_map('absint', $product_ids);
        }

        $ids = get_posts($args);
        $products = [];

        foreach ($ids as $product_id) {
            $product = wc_get_product($product_id);

            if (! $product) {
                continue;
            }

            $images   = $this->get_image_details($product);
            $variants = $this->get_variant_images($product);

            $products[] = [
                'wc_product_id' => $product_id,
                'name'          => $product->get_name(),
                'slug'          => $product->get_slug(),
                'images'        => $images,
                'variant_images' => $variants,
            ];
        }

        return new \WP_REST_Response([
            'product_count' => count($products),
            'image_count'   => array_sum(array_map(function ($p) {
                return count($p['images']) + count($p['variant_images']);
            }, $products)),
            'products'      => $products,
        ], 200);
    }

    /**
     * Serve a single attachment file by its WC attachment ID.
     *
     * Request body (JSON):
     *   { "attachment_id": 12345 }
     *
     * Response: binary file data with Content-Type and Content-Disposition headers.
     */
    public function handle_download(\WP_REST_Request $request): \WP_REST_Response
    {
        $params        = $request->get_json_params();
        $attachment_id = absint($params['attachment_id'] ?? 0);

        if (! $attachment_id) {
            return new \WP_REST_Response(['error' => 'Missing attachment_id.'], 400);
        }

        $file = get_attached_file($attachment_id);

        if (! $file || ! file_exists($file)) {
            return new \WP_REST_Response(['error' => 'File not found.'], 404);
        }

        // Security: ensure the file is within the uploads directory.
        $uploads_dir = wp_get_upload_dir();
        $real_file   = realpath($file);
        $real_base   = realpath($uploads_dir['basedir']);

        if ($real_file === false || $real_base === false || strpos($real_file, $real_base) !== 0) {
            return new \WP_REST_Response(['error' => 'Access denied.'], 403);
        }

        $mime = get_post_mime_type($attachment_id) ?: 'application/octet-stream';
        $name = basename($file);

        // Stream the file directly.
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-store');

        // phpcs:ignore WordPress.WP.AlternativeFunctions
        readfile($file);
        exit;
    }

    /* ------------------------------------------------------------------
     *  Image detail builders
     * ----------------------------------------------------------------*/

    /**
     * Get detailed image info for a product (main + gallery).
     *
     * @return list<array{attachment_id: int, url: string, alt: string, filename: string, mime_type: string, width: int, height: int, position: int}>
     */
    private function get_image_details(\WC_Product $product): array
    {
        $images = [];

        $thumb_id = $product->get_image_id();

        if ($thumb_id) {
            $images[] = $this->build_image_entry((int) $thumb_id, 0);
        }

        $position = 1;
        foreach ($product->get_gallery_image_ids() as $img_id) {
            $images[] = $this->build_image_entry((int) $img_id, $position++);
        }

        return $images;
    }

    /**
     * Get images attached to product variations.
     *
     * @return list<array{wc_variation_id: int, attachment_id: int, url: string, alt: string, filename: string, mime_type: string, width: int, height: int}>
     */
    private function get_variant_images(\WC_Product $product): array
    {
        if (! $product->is_type('variable')) {
            return [];
        }

        $variant_images = [];

        /** @var \WC_Product_Variable $product */
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);

            if (! $variation instanceof \WC_Product_Variation) {
                continue;
            }

            $img_id = $variation->get_image_id();

            if (! $img_id) {
                continue;
            }

            $entry = $this->build_image_entry((int) $img_id, 0);
            $entry['wc_variation_id'] = $child_id;
            $variant_images[] = $entry;
        }

        return $variant_images;
    }

    /**
     * @return array{attachment_id: int, url: string, alt: string, filename: string, mime_type: string, width: int, height: int, position: int}
     */
    private function build_image_entry(int $attachment_id, int $position): array
    {
        $meta = wp_get_attachment_metadata($attachment_id);

        return [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url($attachment_id),
            'alt'           => get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: '',
            'filename'      => basename(get_attached_file($attachment_id) ?: ''),
            'mime_type'     => get_post_mime_type($attachment_id) ?: '',
            'width'         => (int) ($meta['width'] ?? 0),
            'height'        => (int) ($meta['height'] ?? 0),
            'position'      => $position,
        ];
    }
}

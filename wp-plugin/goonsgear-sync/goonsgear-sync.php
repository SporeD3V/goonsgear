<?php

/**
 * Plugin Name: GoonGear Sync
 * Description: Pushes WooCommerce data changes to the GoonGear Laravel application via signed webhooks.
 * Version:     1.0.0
 * Author:      GoonGear
 * Requires PHP: 7.4
 * Requires at least: 5.6
 * WC requires at least: 6.0
 * License:     Proprietary
 */

defined('ABSPATH') || exit;

define('GG_SYNC_VERSION', '1.0.0');
define('GG_SYNC_FILE', __FILE__);
define('GG_SYNC_PATH', plugin_dir_path(__FILE__));
define('GG_SYNC_URL', plugin_dir_url(__FILE__));

require_once GG_SYNC_PATH . 'includes/class-dispatcher.php';
require_once GG_SYNC_PATH . 'includes/class-settings.php';
require_once GG_SYNC_PATH . 'includes/class-bulk-sync.php';
require_once GG_SYNC_PATH . 'includes/class-image-endpoint.php';
require_once GG_SYNC_PATH . 'includes/class-order-sync.php';
require_once GG_SYNC_PATH . 'includes/class-product-sync.php';
require_once GG_SYNC_PATH . 'includes/class-coupon-sync.php';
require_once GG_SYNC_PATH . 'includes/class-customer-sync.php';
require_once GG_SYNC_PATH . 'includes/class-note-sync.php';

/**
 * Create the failed-webhook queue table on activation.
 */
register_activation_hook(__FILE__, function () {
    global $wpdb;

    $table   = $wpdb->prefix . 'gg_sync_queue';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event varchar(100) NOT NULL,
        payload longtext NOT NULL,
        attempts tinyint unsigned NOT NULL DEFAULT 0,
        next_retry_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY next_retry_at (next_retry_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    if (! wp_next_scheduled('gg_sync_process_queue')) {
        wp_schedule_event(time(), 'gg_sync_five_minutes', 'gg_sync_process_queue');
    }

    update_option('gg_sync_version', GG_SYNC_VERSION);
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('gg_sync_process_queue');
});

/**
 * Register a custom 5-minute cron interval.
 */
add_filter('cron_schedules', function ($schedules) {
    $schedules['gg_sync_five_minutes'] = [
        'interval' => 300,
        'display'  => 'Every 5 minutes',
    ];

    return $schedules;
});

/**
 * Boot the plugin once WooCommerce is loaded.
 */
add_action('woocommerce_loaded', function () {
    $dispatcher = new GG_Sync_Dispatcher();

    new GG_Sync_Settings($dispatcher);
    new GG_Sync_Bulk_Sync($dispatcher);
    new GG_Sync_Image_Endpoint();
    new GG_Sync_Order_Sync($dispatcher);
    new GG_Sync_Product_Sync($dispatcher);
    new GG_Sync_Coupon_Sync($dispatcher);
    new GG_Sync_Customer_Sync($dispatcher);
    new GG_Sync_Note_Sync($dispatcher);
});

/**
 * Process the retry queue via WP-Cron.
 */
add_action('gg_sync_process_queue', function () {
    (new GG_Sync_Dispatcher())->process_queue();
});

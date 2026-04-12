<?php

defined('ABSPATH') || exit;

/**
 * Admin settings page for GoonGear Sync.
 */
class GG_Sync_Settings
{
    private GG_Sync_Dispatcher $dispatcher;

    public function __construct(GG_Sync_Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_gg_sync_test_connection', [$this, 'ajax_test_connection']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            'GoonGear Sync',
            'GG Sync',
            'manage_woocommerce',
            'gg-sync',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('gg_sync', 'gg_sync_webhook_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        register_setting('gg_sync', 'gg_sync_webhook_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('gg_sync', 'gg_sync_enabled_domains', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_domains'],
            'default'           => ['order', 'product', 'coupon', 'customer', 'note'],
        ]);
    }

    /**
     * @param  mixed $value
     * @return string[]
     */
    public function sanitize_domains($value): array
    {
        $allowed = ['order', 'product', 'coupon', 'customer', 'note'];

        if (! is_array($value)) {
            return $allowed;
        }

        return array_values(array_intersect($value, $allowed));
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $url     = get_option('gg_sync_webhook_url', '');
        $secret  = get_option('gg_sync_webhook_secret', '');
        $domains = (array) get_option('gg_sync_enabled_domains', ['order', 'product', 'coupon', 'customer', 'note']);
        $log     = array_reverse((array) get_option('gg_sync_activity_log', []));
        $dead    = array_reverse((array) get_option('gg_sync_dead_letters', []));

        $queue_count = $this->get_queue_count();

        ?>
        <div class="wrap">
            <h1>GoonGear Sync</h1>

            <form method="post" action="options.php">
                <?php settings_fields('gg_sync'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="gg_sync_webhook_url">Webhook URL</label></th>
                        <td>
                            <input type="url" id="gg_sync_webhook_url" name="gg_sync_webhook_url"
                                   value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="https://goonsgear.com/webhook/wc-sync" />
                            <p class="description">The Laravel endpoint that will receive webhook events.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gg_sync_webhook_secret">Shared Secret</label></th>
                        <td>
                            <input type="text" id="gg_sync_webhook_secret" name="gg_sync_webhook_secret"
                                   value="<?php echo esc_attr($secret); ?>" class="regular-text" />
                            <p class="description">Used for HMAC-SHA256 signature verification. Must match the Laravel app's <code>WC_SYNC_SECRET</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enabled Domains</th>
                        <td>
                            <?php
                            $all_domains = [
                                'order'    => 'Orders (new, status changes, refunds)',
                                'product'  => 'Products (create, update, stock, delete)',
                                'coupon'   => 'Coupons (create, update, delete)',
                                'customer' => 'Customers (new, profile updates)',
                                'note'     => 'Notes & Tracking (admin notes, shipment tracking)',
                            ];
                            foreach ($all_domains as $key => $label) :
                                ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="gg_sync_enabled_domains[]" value="<?php echo esc_attr($key); ?>"
                                        <?php checked(in_array($key, $domains, true)); ?> />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr />

            <h2>Connection</h2>
            <p>
                <button type="button" id="gg-sync-test" class="button button-secondary">Test Connection</button>
                <span id="gg-sync-test-result" style="margin-left:10px;"></span>
            </p>

            <?php if ($queue_count > 0) : ?>
                <p><strong>Retry queue:</strong> <?php echo (int) $queue_count; ?> pending webhook(s).</p>
            <?php endif; ?>

            <hr />

            <h2>Recent Activity (last 50)</h2>
            <?php if (empty($log)) : ?>
                <p>No webhook activity yet.</p>
            <?php else : ?>
                <table class="widefat fixed striped" style="max-width:600px;">
                    <thead><tr><th>Event</th><th>Time (UTC)</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($log, 0, 20) as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html($entry['event'] ?? ''); ?></td>
                            <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (! empty($dead)) : ?>
                <h2>Dead Letters (last 100)</h2>
                <table class="widefat fixed striped" style="max-width:800px;">
                    <thead><tr><th>Event</th><th>Time</th><th>Payload (truncated)</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($dead, 0, 20) as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html($entry['event'] ?? ''); ?></td>
                            <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                            <td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html($entry['payload'] ?? ''); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        document.getElementById('gg-sync-test')?.addEventListener('click', function () {
            var btn    = this;
            var result = document.getElementById('gg-sync-test-result');
            btn.disabled = true;
            result.textContent = 'Testing…';

            fetch(ajaxurl + '?action=gg_sync_test_connection&_wpnonce=<?php echo esc_js(wp_create_nonce('gg_sync_test')); ?>', {
                method: 'POST',
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                result.textContent = data.data?.message || 'Unknown result';
                result.style.color = data.success ? 'green' : 'red';
                btn.disabled = false;
            })
            .catch(function () {
                result.textContent = 'Request failed.';
                result.style.color = 'red';
                btn.disabled = false;
            });
        });
        </script>
        <?php
    }

    public function ajax_test_connection(): void
    {
        check_ajax_referer('gg_sync_test');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $result = $this->dispatcher->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    private function get_queue_count(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'gg_sync_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}

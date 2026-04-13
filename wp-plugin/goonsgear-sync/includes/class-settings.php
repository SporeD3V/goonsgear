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

            <hr />

            <h2>Bulk Sync</h2>
            <p>Push all WooCommerce data created or modified since a specific date. Use this to close the gap between your last database export and when real-time hooks went live.</p>

            <table class="form-table" id="gg-bulk-sync-form">
                <tr>
                    <th scope="row"><label for="gg-bulk-since">Since Date</label></th>
                    <td>
                        <input type="date" id="gg-bulk-since" value="<?php echo esc_attr(gmdate('Y-m-d', strtotime('-14 days'))); ?>" />
                        <p class="description">Pushes orders, products, coupons, and customers created/modified on or after this date.</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="gg-bulk-start" class="button button-primary">Start Bulk Sync</button>
            </p>

            <div style="max-width:500px;background:#f0f0f0;border-radius:4px;overflow:hidden;margin:10px 0;">
                <div id="gg-bulk-bar" style="width:0%;height:24px;background:#0073aa;transition:width 0.3s;"></div>
            </div>

            <ul id="gg-bulk-log" style="max-height:250px;overflow-y:auto;font-family:monospace;font-size:12px;background:#f9f9f9;border:1px solid #ddd;padding:8px 12px;margin-top:6px;list-style:none;"></ul>

            <hr />

            <h2>Image API</h2>
            <p>The Laravel app can pull product images from this site using authenticated REST endpoints:</p>
            <table class="widefat fixed" style="max-width:700px;">
                <thead><tr><th>Endpoint</th><th>Purpose</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>POST /wp-json/gg-sync/v1/images/manifest</code></td>
                        <td>Returns a list of all product images with attachment IDs, URLs, dimensions, and alt text. Accepts <code>since</code> date or <code>product_ids</code> filter.</td>
                    </tr>
                    <tr>
                        <td><code>POST /wp-json/gg-sync/v1/images/download</code></td>
                        <td>Downloads a single image file by <code>attachment_id</code>. Returns the original file.</td>
                    </tr>
                </tbody>
            </table>
            <p class="description">Both endpoints require the <code>X-GG-Signature</code> header (HMAC-SHA256 of the request body using the shared secret).</p>
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

        /* ---- Bulk Sync ---- */
        var bulkForm   = document.getElementById('gg-bulk-sync-form');
        var bulkBtn    = document.getElementById('gg-bulk-start');
        var bulkLog    = document.getElementById('gg-bulk-log');
        var bulkBar    = document.getElementById('gg-bulk-bar');
        var bulkNonce  = '<?php echo esc_js(wp_create_nonce('gg_sync_bulk')); ?>';
        var domains    = ['order', 'product', 'coupon', 'customer'];

        if (bulkBtn) {
            bulkBtn.addEventListener('click', function () {
                var since  = document.getElementById('gg-bulk-since').value;
                if (!since) { alert('Pick a date first.'); return; }
                bulkBtn.disabled = true;
                bulkLog.innerHTML = '';
                bulkBar.style.width = '0%';

                // 1. Count
                var fd = new FormData();
                fd.append('action', 'gg_sync_bulk_count');
                fd.append('_wpnonce', bulkNonce);
                fd.append('since', since);
                fd.append('domain', 'all');

                log('Counting items since ' + since + '…');

                fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp.success) { log('Error: ' + (resp.data?.message || 'unknown')); bulkBtn.disabled = false; return; }
                    var counts = resp.data.counts;
                    var total  = resp.data.total;
                    log('Found: ' + JSON.stringify(counts) + ' (' + total + ' total)');
                    if (total === 0) { log('Nothing to sync.'); bulkBtn.disabled = false; return; }
                    processDomains(since, counts, total, 0, 0);
                })
                .catch(function (e) { log('Request failed: ' + e.message); bulkBtn.disabled = false; });
            });
        }

        var processed = 0;
        function processDomains(since, counts, total, domainIdx, offset) {
            if (domainIdx >= domains.length) {
                log('Done! Sent ' + processed + ' webhook(s).');
                bulkBar.style.width = '100%';
                bulkBtn.disabled = false;
                return;
            }
            var domain = domains[domainIdx];
            var count  = counts[domain] || 0;
            if (count === 0 || offset >= count) {
                processDomains(since, counts, total, domainIdx + 1, 0);
                return;
            }
            var fd = new FormData();
            fd.append('action', 'gg_sync_bulk_sync');
            fd.append('_wpnonce', bulkNonce);
            fd.append('since', since);
            fd.append('domain', domain);
            fd.append('offset', offset);

            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success) { log('Error on ' + domain + ': ' + (resp.data?.message || 'unknown')); bulkBtn.disabled = false; return; }
                var sent = resp.data.sent;
                processed += sent;
                var pct = Math.round((processed / total) * 100);
                bulkBar.style.width = pct + '%';
                log(domain + ' batch @ offset ' + offset + ': sent ' + sent);
                var nextOffset = offset + resp.data.batch_size;
                if (sent > 0 && nextOffset < count) {
                    processDomains(since, counts, total, domainIdx, nextOffset);
                } else {
                    processDomains(since, counts, total, domainIdx + 1, 0);
                }
            })
            .catch(function (e) { log('Request failed: ' + e.message); bulkBtn.disabled = false; });
        }

        function log(msg) {
            var li = document.createElement('li');
            li.textContent = new Date().toLocaleTimeString() + ' — ' + msg;
            bulkLog.appendChild(li);
            bulkLog.scrollTop = bulkLog.scrollHeight;
        }
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

<?php

defined('ABSPATH') || exit;

/**
 * HMAC-signed webhook dispatcher with retry queue.
 */
class GG_Sync_Dispatcher
{
    private const MAX_ATTEMPTS     = 5;
    private const TIMEOUT_SECONDS  = 15;

    /** Backoff schedule in seconds: 5m, 15m, 1h, 6h. */
    private const BACKOFF = [300, 900, 3600, 21600];

    /** Prevent the same event+ID from firing twice in one request. */
    private array $sent_this_request = [];

    /**
     * Send a webhook event to the configured endpoint.
     *
     * @param  string $event  Dot-notation event name (e.g. "order.created").
     * @param  array<string, mixed> $data   Payload data.
     * @param  string|null $dedup_key  Optional key to prevent duplicate sends in the same request.
     */
    public function send(string $event, array $data, ?string $dedup_key = null): bool
    {
        if (! $this->is_enabled($event)) {
            return false;
        }

        if ($dedup_key !== null) {
            if (isset($this->sent_this_request[$dedup_key])) {
                return true;
            }
            $this->sent_this_request[$dedup_key] = true;
        }

        $url    = get_option('gg_sync_webhook_url', '');
        $secret = get_option('gg_sync_webhook_secret', '');

        if (empty($url) || empty($secret)) {
            return false;
        }

        $payload = wp_json_encode([
            'event'     => $event,
            'timestamp' => gmdate('c'),
            'data'      => $data,
        ]);

        return $this->deliver($url, $secret, $event, $payload);
    }

    /**
     * Fire a test ping to verify the connection.
     */
    public function test_connection(): array
    {
        $url    = get_option('gg_sync_webhook_url', '');
        $secret = get_option('gg_sync_webhook_secret', '');

        if (empty($url) || empty($secret)) {
            return ['success' => false, 'message' => 'Webhook URL or secret is not configured.'];
        }

        $payload = wp_json_encode([
            'event'     => 'ping',
            'timestamp' => gmdate('c'),
            'data'      => ['version' => GG_SYNC_VERSION],
        ]);

        $ok = $this->deliver($url, $secret, 'ping', $payload, true);

        return $ok
            ? ['success' => true, 'message' => 'Connection successful.']
            : ['success' => false, 'message' => 'Failed to reach the endpoint. Check the URL and secret.'];
    }

    /**
     * Process queued webhooks that are ready for retry.
     */
    public function process_queue(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'gg_sync_queue';
        $now   = gmdate('Y-m-d H:i:s');
        $url   = get_option('gg_sync_webhook_url', '');
        $secret = get_option('gg_sync_webhook_secret', '');

        if (empty($url) || empty($secret)) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE attempts < %d AND next_retry_at <= %s ORDER BY created_at ASC LIMIT 50",
                self::MAX_ATTEMPTS,
                $now
            )
        );

        foreach ($rows as $row) {
            $event   = $row->event;
            $payload = $row->payload;
            $ok      = $this->deliver($url, $secret, $event, $payload, true);

            if ($ok) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->delete($table, ['id' => $row->id], ['%d']);
            } else {
                $attempt = (int) $row->attempts + 1;

                if ($attempt >= self::MAX_ATTEMPTS) {
                    $this->log_dead_letter($row);
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->delete($table, ['id' => $row->id], ['%d']);
                } else {
                    $delay    = self::BACKOFF[$attempt - 1] ?? 21600;
                    $next_at  = gmdate('Y-m-d H:i:s', time() + $delay);

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->update(
                        $table,
                        ['attempts' => $attempt, 'next_retry_at' => $next_at],
                        ['id' => $row->id],
                        ['%d', '%s'],
                        ['%d']
                    );
                }
            }
        }
    }

    /* ------------------------------------------------------------------
     *  Internal
     * ----------------------------------------------------------------*/

    private function deliver(string $url, string $secret, string $event, string $payload, bool $skip_queue = false): bool
    {
        $signature = hash_hmac('sha256', $payload, $secret);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'   => 'application/json',
                'X-GG-Signature' => $signature,
                'X-GG-Event'     => $event,
            ],
            'body'      => $payload,
            'timeout'   => self::TIMEOUT_SECONDS,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            if (! $skip_queue) {
                $this->queue_retry($event, $payload);
            }

            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 400) {
            if (! $skip_queue) {
                $this->queue_retry($event, $payload);
            }

            return false;
        }

        $this->log_success($event);

        return true;
    }

    private function queue_retry(string $event, string $payload): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'gg_sync_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, [
            'event'         => $event,
            'payload'       => $payload,
            'attempts'      => 0,
            'next_retry_at' => gmdate('Y-m-d H:i:s', time() + self::BACKOFF[0]),
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%s', '%d', '%s', '%s']);
    }

    private function log_success(string $event): void
    {
        $log   = get_option('gg_sync_activity_log', []);
        $log[] = [
            'event' => $event,
            'time'  => gmdate('c'),
            'ok'    => true,
        ];

        // Keep only the last 50 entries.
        $log = array_slice($log, -50);
        update_option('gg_sync_activity_log', $log, false);
    }

    private function log_dead_letter(object $row): void
    {
        $log   = get_option('gg_sync_dead_letters', []);
        $log[] = [
            'event'   => $row->event,
            'payload' => mb_substr($row->payload, 0, 500),
            'time'    => gmdate('c'),
        ];

        $log = array_slice($log, -100);
        update_option('gg_sync_dead_letters', $log, false);
    }

    /**
     * Check whether a given event domain is enabled.
     */
    private function is_enabled(string $event): bool
    {
        $domain   = explode('.', $event)[0] ?? '';
        $enabled  = get_option('gg_sync_enabled_domains', [
            'order', 'product', 'coupon', 'customer', 'note',
        ]);

        return in_array($domain, (array) $enabled, true);
    }
}

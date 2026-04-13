<?php

namespace Tests\Feature;

use App\Models\WcSyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WcSyncWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret-abc123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.wc_sync.webhook_secret' => $this->secret]);
    }

    private function signPayload(string $jsonBody): string
    {
        return hash_hmac('sha256', $jsonBody, $this->secret);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postWebhook(array $body, ?string $signature = null): TestResponse
    {
        $json = json_encode($body);
        $signature ??= $this->signPayload($json);

        return $this->call(
            'POST',
            route('webhooks.wc-sync'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_GG_SIGNATURE' => $signature,
            ],
            $json,
        );
    }

    /* ---------------------------------------------------------------
     *  Signature verification
     * ---------------------------------------------------------------*/

    public function test_missing_signature_returns_401(): void
    {
        $json = json_encode(['event' => 'ping', 'data' => []]);

        $response = $this->call(
            'POST',
            route('webhooks.wc-sync'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $json,
        );

        $response->assertStatus(401);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $response = $this->postWebhook(
            ['event' => 'ping', 'data' => []],
            'bad-signature-value',
        );

        $response->assertStatus(403);
    }

    public function test_valid_signature_passes_through(): void
    {
        $response = $this->postWebhook(['event' => 'ping', 'data' => []]);

        $response->assertOk();
    }

    /* ---------------------------------------------------------------
     *  Ping
     * ---------------------------------------------------------------*/

    public function test_ping_returns_pong_without_storing(): void
    {
        $response = $this->postWebhook(['event' => 'ping', 'data' => []]);

        $response->assertOk();
        $response->assertJson(['status' => 'pong']);
        $this->assertDatabaseCount('wc_sync_payloads', 0);
    }

    /* ---------------------------------------------------------------
     *  Payload storage
     * ---------------------------------------------------------------*/

    public function test_missing_event_returns_422(): void
    {
        $response = $this->postWebhook(['data' => ['foo' => 'bar']]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Missing event.']);
    }

    public function test_order_event_stores_payload(): void
    {
        $response = $this->postWebhook([
            'event' => 'order.created',
            'timestamp' => '2026-04-13T10:00:00+00:00',
            'data' => [
                'wc_order_id' => 5001,
                'order_number' => '#5001',
                'total' => 99.99,
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'queued']);

        $this->assertDatabaseCount('wc_sync_payloads', 1);

        $payload = WcSyncPayload::first();
        $this->assertSame('order.created', $payload->event);
        $this->assertSame('order', $payload->wc_entity_type);
        $this->assertSame(5001, $payload->wc_entity_id);
        $this->assertNull($payload->processed_at);
        $this->assertSame(5001, $payload->payload['data']['wc_order_id']);
    }

    public function test_product_event_stores_with_correct_entity(): void
    {
        $this->postWebhook([
            'event' => 'product.updated',
            'data' => ['wc_product_id' => 2001, 'name' => 'Test Hoodie'],
        ]);

        $payload = WcSyncPayload::first();
        $this->assertSame('product', $payload->wc_entity_type);
        $this->assertSame(2001, $payload->wc_entity_id);
    }

    public function test_coupon_event_stores_with_correct_entity(): void
    {
        $this->postWebhook([
            'event' => 'coupon.saved',
            'data' => ['wc_coupon_id' => 301, 'code' => 'SUMMER20'],
        ]);

        $payload = WcSyncPayload::first();
        $this->assertSame('coupon', $payload->wc_entity_type);
        $this->assertSame(301, $payload->wc_entity_id);
    }

    public function test_customer_event_stores_with_correct_entity(): void
    {
        $this->postWebhook([
            'event' => 'customer.created',
            'data' => ['wc_user_id' => 42, 'email' => 'john@example.com'],
        ]);

        $payload = WcSyncPayload::first();
        $this->assertSame('customer', $payload->wc_entity_type);
        $this->assertSame(42, $payload->wc_entity_id);
    }

    public function test_note_event_stores_with_order_id(): void
    {
        $this->postWebhook([
            'event' => 'note.created',
            'data' => ['wc_order_id' => 5555, 'content' => 'Item shipped'],
        ]);

        $payload = WcSyncPayload::first();
        $this->assertSame('note', $payload->wc_entity_type);
        $this->assertSame(5555, $payload->wc_entity_id);
    }

    public function test_unknown_event_prefix_still_stores(): void
    {
        $this->postWebhook([
            'event' => 'custom.event',
            'data' => ['foo' => 'bar'],
        ]);

        $this->assertDatabaseCount('wc_sync_payloads', 1);

        $payload = WcSyncPayload::first();
        $this->assertSame('custom.event', $payload->event);
        $this->assertNull($payload->wc_entity_type);
        $this->assertNull($payload->wc_entity_id);
    }
}

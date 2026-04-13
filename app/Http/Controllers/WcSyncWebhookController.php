<?php

namespace App\Http\Controllers;

use App\Models\WcSyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WcSyncWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $body = $request->json()->all();

        $event = $body['event'] ?? null;
        $data = $body['data'] ?? [];

        if (! $event) {
            return response()->json(['error' => 'Missing event.'], 422);
        }

        // Respond to pings immediately without storing.
        if ($event === 'ping') {
            return response()->json(['status' => 'pong']);
        }

        // Derive entity type and ID from the event name and payload.
        [$entityType, $entityId] = $this->resolveEntity($event, $data);

        WcSyncPayload::create([
            'event' => $event,
            'wc_entity_type' => $entityType,
            'wc_entity_id' => $entityId,
            'payload' => $body,
            'received_at' => now(),
        ]);

        return response()->json(['status' => 'queued']);
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveEntity(string $event, array $data): array
    {
        $prefix = explode('.', $event)[0] ?? '';

        return match ($prefix) {
            'order' => ['order', $data['wc_order_id'] ?? null],
            'product' => ['product', $data['wc_product_id'] ?? null],
            'coupon' => ['coupon', $data['wc_coupon_id'] ?? null],
            'customer' => ['customer', $data['wc_user_id'] ?? null],
            'note' => ['note', $data['wc_order_id'] ?? null],
            default => [null, null],
        };
    }
}

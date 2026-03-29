<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GeoDbLocationProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(private readonly GeoDbLocationProvider $locationProvider) {}

    public function states(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'country' => ['required', 'string', 'size:2'],
        ]);

        return response()->json([
            'data' => $this->locationProvider->states($payload['country']),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json([
            'data' => $this->locationProvider->cities($payload['country'], $payload['state'] ?? null),
        ]);
    }
}

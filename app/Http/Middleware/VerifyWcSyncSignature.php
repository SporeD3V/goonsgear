<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWcSyncSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.wc_sync.webhook_secret');
        $signature = $request->header('X-GG-Signature');

        if (empty($secret) || empty($signature)) {
            abort(401, 'Missing authentication.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }
}

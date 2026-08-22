<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * PayPal's JS SDK loads from www.paypal.com (or www.sandbox.paypal.com),
     * pulls assets from paypalobjects.com, calls its own API, and opens the
     * approval flow in a popup or iframe. Every one of those needs an explicit
     * source here, otherwise the button silently never renders.
     *
     * @var list<string>
     */
    private const PAYPAL_SOURCES = [
        'https://*.paypal.com',
        'https://*.paypalobjects.com',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // same-origin would null out window.opener for the PayPal popup, which
        // breaks the approval handshake. allow-popups keeps the isolation for
        // everything else.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $paypal = implode(' ', self::PAYPAL_SOURCES);

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$paypal}",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: {$paypal}",
            "font-src 'self' data:",
            "connect-src 'self' {$paypal}",
            "frame-src 'self' {$paypal}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com",
        ];

        return implode('; ', $directives);
    }
}

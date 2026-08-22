<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function directives(string $uri = '/'): array
    {
        $policy = (string) $this->get($uri)->headers->get('Content-Security-Policy');

        return array_map(trim(...), explode(';', $policy));
    }

    private function directive(string $name): string
    {
        foreach ($this->directives() as $directive) {
            if (str_starts_with($directive, $name.' ')) {
                return $directive;
            }
        }

        return '';
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotSame('', (string) $response->headers->get('Content-Security-Policy'));
    }

    /**
     * The PayPal JS SDK is loaded from paypal.com and renders the button into
     * the page. Without these sources the button silently never appears and
     * checkout offers no PayPal option at all.
     */
    public function test_content_security_policy_allows_the_paypal_sdk(): void
    {
        foreach (['script-src', 'connect-src', 'frame-src', 'img-src'] as $name) {
            $this->assertStringContainsString(
                'https://*.paypal.com',
                $this->directive($name),
                "{$name} must allow the PayPal SDK",
            );
        }
    }

    public function test_content_security_policy_still_locks_down_defaults(): void
    {
        $this->assertSame("default-src 'self'", $this->directive('default-src'));
        $this->assertSame("frame-ancestors 'none'", $this->directive('frame-ancestors'));
        $this->assertSame("base-uri 'self'", $this->directive('base-uri'));
    }

    /**
     * A bare same-origin policy nulls window.opener for the PayPal popup and
     * breaks the approval handshake.
     */
    public function test_cross_origin_opener_policy_allows_popups(): void
    {
        $this->get('/')->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
    }
}

<?php

namespace App\Support;

class DhlTracking
{
    public function trackingUrl(?string $trackingNumber): ?string
    {
        $normalizedTrackingNumber = trim((string) $trackingNumber);

        if ($normalizedTrackingNumber === '') {
            return null;
        }

        return str_replace(
            '%s',
            rawurlencode($normalizedTrackingNumber),
            (string) config('services.dhl.tracking_url', 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s&submit=1'),
        );
    }
}

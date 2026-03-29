<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCartSetting;
use App\Models\CartAbandonment;
use App\Models\Coupon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'app:send-abandoned-cart-reminders';

    protected $description = 'Send reminder emails for carts abandoned over an hour ago';

    public function handle(): int
    {
        $settings = AbandonedCartSetting::current();

        if (! $settings->is_enabled) {
            $this->info('Abandoned cart reminders are disabled in admin settings.');

            return self::SUCCESS;
        }

        $configuredCoupon = null;

        if ($settings->coupon_code !== null && $settings->coupon_code !== '') {
            $configuredCoupon = Coupon::query()
                ->where('code', strtoupper($settings->coupon_code))
                ->where('is_active', true)
                ->first();
        }

        $count = 0;

        CartAbandonment::query()
            ->whereNull('reminder_sent_at')
            ->whereNull('recovered_at')
            ->where('abandoned_at', '<=', now()->subMinutes($settings->delay_minutes))
            ->each(function (CartAbandonment $abandonment) use (&$count, $configuredCoupon): void {
                $couponCode = null;

                if ($configuredCoupon instanceof Coupon) {
                    $subtotal = collect($abandonment->cart_data)->sum(static function (array $item): float {
                        $price = (float) ($item['price'] ?? 0);
                        $quantity = (int) ($item['quantity'] ?? 0);

                        return $price * $quantity;
                    });

                    if ($configuredCoupon->validationError($subtotal) === null) {
                        $couponCode = $configuredCoupon->code;
                    }
                }

                Mail::to($abandonment->email)->send(new AbandonedCartReminder($abandonment, $couponCode));
                $abandonment->update(['reminder_sent_at' => now()]);
                $count++;
            });

        $this->info("Sent {$count} abandoned cart reminder(s).");

        return self::SUCCESS;
    }
}

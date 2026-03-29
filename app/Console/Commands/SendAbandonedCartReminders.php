<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartReminder;
use App\Models\CartAbandonment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'app:send-abandoned-cart-reminders';

    protected $description = 'Send reminder emails for carts abandoned over an hour ago';

    public function handle(): int
    {
        $count = 0;

        CartAbandonment::query()
            ->whereNull('reminder_sent_at')
            ->whereNull('recovered_at')
            ->where('abandoned_at', '<=', now()->subHour())
            ->each(function (CartAbandonment $abandonment) use (&$count): void {
                Mail::to($abandonment->email)->send(new AbandonedCartReminder($abandonment));
                $abandonment->update(['reminder_sent_at' => now()]);
                $count++;
            });

        $this->info("Sent {$count} abandoned cart reminder(s).");

        return self::SUCCESS;
    }
}

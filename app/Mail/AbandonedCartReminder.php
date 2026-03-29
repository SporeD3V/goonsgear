<?php

namespace App\Mail;

use App\Models\CartAbandonment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public readonly CartAbandonment $abandonment, public readonly ?string $couponCode = null)
    {
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You left something in your cart',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.carts.abandoned',
        );
    }
}

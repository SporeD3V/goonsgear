<?php

namespace App\Mail;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CartItemDiscounted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ProductVariant $variant,
        public readonly float $oldPrice,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Good news — an item in your cart just got cheaper!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.carts.discounted',
        );
    }

    /**
     * @return array<int, never>
     */
    public function attachments(): array
    {
        return [];
    }

    public function headers(): Headers
    {
        return new Headers(
            text: ['List-Unsubscribe' => '<'.route('account.index').'#notifications>'],
        );
    }
}

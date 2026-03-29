<?php

namespace App\Mail;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStockAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ProductVariant $variant,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Back in stock: '.$this->variant->product?->name.' ('.$this->variant->name.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.stock.back-in-stock',
        );
    }

    /**
     * @return array<int, never>
     */
    public function attachments(): array
    {
        return [];
    }
}

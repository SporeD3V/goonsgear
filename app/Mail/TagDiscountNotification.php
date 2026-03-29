<?php

namespace App\Mail;

use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TagDiscountNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Tag $tag,
        public readonly ProductVariant $variant,
        public readonly float $oldPrice,
        public readonly float $newPrice,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Discount alert: '.$this->tag->name.' - '.$this->variant->product?->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tags.discount',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

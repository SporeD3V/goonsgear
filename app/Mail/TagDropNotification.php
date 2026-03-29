<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TagDropNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Tag $tag,
        public readonly Product $product,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New drop: '.$this->tag->name.' - '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tags.drop',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

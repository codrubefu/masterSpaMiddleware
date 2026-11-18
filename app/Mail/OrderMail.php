<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic order email (invoice, booking confirmation, vouchers).
 * Only plain arrays + file paths are kept so it can be queued.
 */
class OrderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $data   view data
     * @param string[]             $files  absolute paths to attach
     */
    public function __construct(
        protected string $mailSubject,
        protected string $mailView,
        protected array $data = [],
        protected array $files = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: $this->mailView, with: $this->data);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(fn (string $file) => Attachment::fromPath($file), $this->files);
    }
}

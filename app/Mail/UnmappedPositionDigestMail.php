<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnmappedPositionDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $positions)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[A-IDP] ตำแหน่งยังไม่ผูกสมรรถนะ');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.digests.unmapped-positions',
            with: [
                'positions' => $this->positions,
                'actionUrl' => route('dashboard'),
            ],
        );
    }
}

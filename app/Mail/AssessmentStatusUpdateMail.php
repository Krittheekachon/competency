<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $employee,
        public string $status,
        public string $actionUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[A-IDP] อัปเดตสถานะผลการประเมิน',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.assessment-status-update',
            with: [
                'employee' => $this->employee,
                'status' => $this->status,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}

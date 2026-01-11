<?php

namespace App\Mail;

use App\Models\UserReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public $report;
    public $responseContent;

    /**
     * Create a new message instance.
     */
    public function __construct(UserReport $report, string $responseContent)
    {
        $this->report = $report;
        $this->responseContent = $responseContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Risposta alla tua segnalazione #' . $this->report->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reports.response',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

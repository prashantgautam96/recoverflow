<?php

namespace App\Mail;

use App\Models\InvoiceReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InvoiceReminder $reminder) {}

    public function envelope(): Envelope
    {
        $subject = $this->reminder->subject ?: "Payment reminder for {$this->reminder->invoice?->invoice_number}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-reminder',
            with: [
                'reminder' => $this->reminder,
                'invoice' => $this->reminder->invoice,
                'client' => $this->reminder->invoice?->client,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

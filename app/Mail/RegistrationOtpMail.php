<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $otp,
        public int $expiresInMinutes
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your RecoverFlow verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-otp',
            with: [
                'name' => $this->name,
                'otp' => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $userName,
        public string $purpose = 'verify'
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->purpose === 'update' 
            ? 'Email Update Verification Code - Privatily'
            : 'Email Verification Code - Privatily';
        
        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification-code',
            with: [
                'code' => $this->code,
                'userName' => $this->userName,
                'purpose' => $this->purpose,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

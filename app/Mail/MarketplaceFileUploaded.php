<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MarketplaceFileUploaded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function envelope(): Envelope
    {
        $serviceName = $this->notification->data['service_name'] ?? 'Marketplace Service';
        
        return new Envelope(
            subject: "Document Provided - {$serviceName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketplace-file-uploaded',
            with: ['notification' => $this->notification],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}


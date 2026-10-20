<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemporaryGuestBookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public \App\Models\TemporaryGuestBooking $tempBooking;
    public string $rawToken;

    /**
     * Create a new message instance.
     */
    public function __construct(\App\Models\TemporaryGuestBooking $tempBooking, string $rawToken)
    {
        $this->tempBooking = $tempBooking;
        $this->rawToken = $rawToken;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Raflora Booking Request Received - Action Required to Claim',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.temporary-guest-booking',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

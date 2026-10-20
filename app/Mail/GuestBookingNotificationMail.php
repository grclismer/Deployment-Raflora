<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestBookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $context;

    /**
     * Create a new message instance.
     */
    public function __construct($booking, string $context = 'confirmation')
    {
        $this->booking = $booking;
        $this->context = $context;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->context) {
            'confirmation' => 'Your Booking Request is Received - Raflora Enterprises',
            'quote_updated' => 'Your Quotation has been Updated - Raflora Enterprises',
            'status_updated' => 'Booking Status Update: ' . $this->booking->status_display_label,
            default => 'Booking Update - Raflora Enterprises',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-booking-notification',
            with: [
                'booking' => $this->booking,
                'context' => $this->context,
            ]
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

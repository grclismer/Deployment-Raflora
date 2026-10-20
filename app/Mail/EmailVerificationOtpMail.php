<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $userName;
    public int $expireMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otp, string $userName = 'Valued Client', int $expireMinutes = 10)
    {
        $this->otp = $otp;
        $this->userName = $userName;
        $this->expireMinutes = $expireMinutes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name', 'Raflora Enterprises') . ' — Email Verification Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-otp',
            with: [
                'otp' => $this->otp,
                'userName' => $this->userName,
                'expireMinutes' => $this->expireMinutes,
            ],
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

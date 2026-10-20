<?php

namespace Tests\Feature;

use App\Mail\TemporaryGuestBookingMail;
use App\Models\TemporaryGuestBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemporaryGuestBookingMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_guest_booking_mail_renders_without_error()
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);

        $mail = new TemporaryGuestBookingMail($temp, $token);

        // Assert that the mailable can be rendered into a string without throwing ViewException
        $rendered = $mail->render();

        $this->assertStringContainsString('Action Required: Claim Your Booking Request', $rendered);
        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertStringContainsString('register', $rendered);
    }
}

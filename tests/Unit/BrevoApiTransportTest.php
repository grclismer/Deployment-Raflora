<?php

namespace Tests\Unit;

use App\Mail\BookingStatusChanged;
use App\Mail\EmailVerificationOtpMail;
use App\Mail\GuestBookingNotificationMail;
use App\Mail\TemporaryGuestBookingMail;
use App\Models\Booking;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\BrevoApiTransport;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BrevoApiTransportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement 1: Successful Brevo submission
     * - HTTP fake returns 201
     * - messageId returned
     * - correct endpoint
     * - sender
     * - recipient
     * - subject
     * - HTML body
     */
    public function test_successful_brevo_submission_posts_correct_payload_and_sets_message_id(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'messageId' => '<202610081234.test@smtp-relay.brevo.com>',
            ], 201),
        ]);

        $transport = new BrevoApiTransport('xkeysib-test-mock-key-12345');

        $email = (new Email())
            ->from(new Address('rafloraenterprise@gmail.com', 'Raflora Enterprises'))
            ->to(new Address('client@example.com', 'Jane Doe'))
            ->subject('Your Booking Confirmation')
            ->html('<p>Welcome to Raflora Enterprises!</p>')
            ->text('Welcome to Raflora Enterprises!');

        $envelope = new Envelope(
            new Address('rafloraenterprise@gmail.com', 'Raflora Enterprises'),
            [new Address('client@example.com', 'Jane Doe')]
        );

        $sentMessage = $transport->send($email, $envelope);

        $this->assertNotNull($sentMessage);
        $this->assertSame('<202610081234.test@smtp-relay.brevo.com>', $sentMessage->getMessageId());

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->header('api-key')[0] === 'xkeysib-test-mock-key-12345'
                && $request->header('Accept')[0] === 'application/json'
                && $data['sender']['email'] === 'rafloraenterprise@gmail.com'
                && $data['sender']['name'] === 'Raflora Enterprises'
                && count($data['to']) === 1
                && $data['to'][0]['email'] === 'client@example.com'
                && $data['to'][0]['name'] === 'Jane Doe'
                && $data['subject'] === 'Your Booking Confirmation'
                && str_contains($data['htmlContent'], 'Welcome to Raflora Enterprises!')
                && str_contains($data['textContent'], 'Welcome to Raflora Enterprises!');
        });
    }

    /**
     * Requirement 2: Brevo 4xx/5xx failure
     * - correct exception thrown
     * - no secret leakage in exception or logs
     */
    public function test_brevo_http_failure_throws_transport_exception_without_leaking_secret(): void
    {
        $mockKey = 'xkeysib-secret-key-to-protect-99999';

        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'code' => 'unauthorized',
                'message' => 'Key ' . $mockKey . ' is invalid or expired',
            ], 401),
        ]);

        $transport = new BrevoApiTransport($mockKey);

        $email = (new Email())
            ->from('rafloraenterprise@gmail.com')
            ->to('client@example.com')
            ->subject('Test')
            ->text('Test Body');

        try {
            $transport->send($email);
            $this->fail('Expected TransportException was not thrown on HTTP 401');
        } catch (TransportException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertStringNotContainsString($mockKey, $e->getMessage(), 'Secret API key must not leak in exception message');
        }
    }

    /**
     * Requirement 3: Missing API key
     * - safe failure
     * - no secret output
     */
    public function test_missing_api_key_throws_transport_exception_safely(): void
    {
        config(['services.brevo.key' => null]);

        $transport = new BrevoApiTransport(null);

        $email = (new Email())
            ->from('rafloraenterprise@gmail.com')
            ->to('client@example.com')
            ->subject('Test Missing Key')
            ->text('Test Body');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo API key is not configured.');

        $transport->send($email);
    }

    /**
     * Requirement 4: Laravel Mail integration
     * - brevo_api transport is recognized by MailManager
     */
    public function test_laravel_mail_recognizes_brevo_api_transport(): void
    {
        config([
            'mail.default' => 'brevo_api',
            'mail.mailers.brevo_api.transport' => 'brevo_api',
            'services.brevo.key' => 'xkeysib-mock-test-key-laravel-integration',
        ]);

        $mailer = Mail::mailer('brevo_api');
        $this->assertNotNull($mailer);

        $symfonyTransport = $mailer->getSymfonyTransport();
        $this->assertInstanceOf(BrevoApiTransport::class, $symfonyTransport);
        $this->assertSame('brevo_api', (string) $symfonyTransport);
    }

    /**
     * Requirement 5: Existing OTP path
     * - USE_PHPMAILER=false
     * - OtpService uses Laravel Mail (which dispatches via brevo_api)
     */
    public function test_otp_service_uses_laravel_mail_when_phpmailer_is_disabled(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'messageId' => '<otp-message-id-12345@smtp-relay.brevo.com>',
            ], 201),
        ]);

        config([
            'mail.default' => 'brevo_api',
            'mail.use_phpmailer' => false,
            'services.brevo.key' => 'xkeysib-test-otp-flow-key',
        ]);

        $user = User::factory()->create([
            'email' => 'otp.recipient@example.com',
            'first_name' => 'John',
        ]);

        $otpService = app(OtpService::class);
        $otp = $otpService->generate($user, 'email_verification');

        $sent = $otpService->sendOtp($user, $otp, 10, 'email_verification');

        $this->assertTrue($sent, 'Expected OtpService::sendOtp to succeed via Laravel Mail brevo_api transport');

        Http::assertSent(function (Request $request) use ($otp) {
            $data = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $data['to'][0]['email'] === 'otp.recipient@example.com'
                && str_contains($data['htmlContent'], $otp);
        });
    }

    /**
     * Requirement 6: Existing Mailables remain compatible
     * - EmailVerificationOtpMail
     * - BookingStatusChanged
     * - GuestBookingNotificationMail
     * - TemporaryGuestBookingMail
     */
    public function test_existing_mailables_render_and_send_via_brevo_transport(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'messageId' => '<mailable-test-id@smtp-relay.brevo.com>',
            ], 201),
        ]);

        config([
            'mail.default' => 'brevo_api',
            'services.brevo.key' => 'xkeysib-test-mailable-key',
            'mail.from.address' => 'rafloraenterprise@gmail.com',
            'mail.from.name' => 'Raflora Enterprises',
        ]);

        // 1. EmailVerificationOtpMail
        Mail::to('client1@example.com')->send(new EmailVerificationOtpMail('123456', 'Alice', 10));

        // 2. BookingStatusChanged
        $user = User::factory()->create();
        $client = \App\Models\Client::create([
            'user_id' => $user->id,
            'full_name' => 'Test Client',
            'email' => 'client2@example.com',
            'phone' => '09170000000',
        ]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'guest_name' => 'Guest User',
            'guest_access_token' => 'guest-token-123',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'The Garden',
            'status' => 'confirmed',
            'total_quoted' => 1000,
        ]);
        Mail::to('client2@example.com')->send(new BookingStatusChanged($booking, null, 'payment_pending'));

        // 3. GuestBookingNotificationMail
        Mail::to('guest@example.com')->send(new GuestBookingNotificationMail($booking, 'confirmation'));

        // 4. TemporaryGuestBookingMail
        $tempBooking = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', 'secure-claim-token-123'),
            'guest_name' => 'John Doe',
            'guest_email' => 'temp@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => now()->addHours(24),
        ]);
        Mail::to('temp@example.com')->send(new TemporaryGuestBookingMail($tempBooking, 'secure-claim-token-123'));

        Http::assertSentCount(4);
    }

    /**
     * Additional Check: CC, BCC, and Reply-To headers mapping
     */
    public function test_cc_bcc_and_reply_to_are_mapped_correctly(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'messageId' => '<headers-test-id@smtp-relay.brevo.com>',
            ], 201),
        ]);

        $transport = new BrevoApiTransport('xkeysib-mock-headers-key');

        $email = (new Email())
            ->from('rafloraenterprise@gmail.com')
            ->to('primary@example.com')
            ->cc('manager@example.com')
            ->bcc('archive@example.com')
            ->replyTo('support@example.com')
            ->subject('Header Mapping Test')
            ->text('Testing header mappings');

        $transport->send($email);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return isset($data['cc'])
                && $data['cc'][0]['email'] === 'manager@example.com'
                && isset($data['bcc'])
                && $data['bcc'][0]['email'] === 'archive@example.com'
                && isset($data['replyTo'])
                && $data['replyTo']['email'] === 'support@example.com';
        });
    }

    /**
     * Additional Check: Connection timeout throws TransportException
     */
    public function test_connection_exception_throws_transport_exception(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $transport = new BrevoApiTransport('xkeysib-mock-key');

        $email = (new Email())
            ->from('rafloraenterprise@gmail.com')
            ->to('client@example.com')
            ->subject('Timeout Test')
            ->text('Body');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo API connection failure');

        $transport->send($email);
    }

    /**
     * Additional Check: Malformed response throws TransportException
     */
    public function test_malformed_response_without_message_id_throws_transport_exception(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        $transport = new BrevoApiTransport('xkeysib-mock-key');

        $email = (new Email())
            ->from('rafloraenterprise@gmail.com')
            ->to('client@example.com')
            ->subject('Malformed Test')
            ->text('Body');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('missing messageId');

        $transport->send($email);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class BrevoApiTransport extends AbstractTransport
{
    public const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    protected ?string $apiKey;
    protected float $timeout;
    protected float $connectTimeout;

    public function __construct(
        ?string $apiKey = null,
        ?float $timeout = null,
        ?float $connectTimeout = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($dispatcher, $logger);

        $this->apiKey = $apiKey ?? config('services.brevo.key');
        $this->timeout = $timeout ?? (float) config('services.brevo.timeout', 15);
        $this->connectTimeout = $connectTimeout ?? (float) config('services.brevo.connect_timeout', 5);
    }

    /**
     * Get the configured Brevo API key.
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Set the Brevo API key.
     */
    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Get request timeout in seconds.
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Get connection timeout in seconds.
     */
    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /**
     * Transmit the email via Brevo's HTTPS transactional API.
     *
     * @throws TransportException
     */
    protected function doSend(SentMessage $message): void
    {
        if (empty($this->apiKey)) {
            Log::error('Brevo API transport: API key is not configured.');
            throw new TransportException('Brevo API key is not configured.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $payload = $this->buildPayload($email);

        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->post(self::ENDPOINT, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Brevo API transport connection failure: timeout or network unreachable.');
            throw new TransportException('Brevo API connection failure: timeout or unreachable endpoint.', 0, $e);
        } catch (\Throwable $e) {
            Log::error('Brevo API transport request exception', [
                'exception_class' => get_class($e),
            ]);
            throw new TransportException('Brevo API request failed due to client error.', 0, $e);
        }

        if (! $response->successful()) {
            $status = $response->status();
            $data = $response->json();
            $code = is_array($data) && isset($data['code']) ? $data['code'] : 'unknown_error';

            Log::error('Brevo API transport HTTP error', [
                'status' => $status,
                'code' => $code,
            ]);

            throw new TransportException(
                sprintf('Unable to send email via Brevo API: HTTP %d (%s).', $status, $code),
                $status
            );
        }

        $responseData = $response->json();
        if (! is_array($responseData) || empty($responseData['messageId'])) {
            Log::error('Brevo API transport received malformed response', [
                'status' => $response->status(),
            ]);
            throw new TransportException(
                sprintf('Brevo API returned malformed response: missing messageId, got HTTP %d.', $response->status())
            );
        }

        $message->setMessageId((string) $responseData['messageId']);
    }

    /**
     * Transform a Symfony Email instance into Brevo's JSON payload.
     *
     * @return array<string, mixed>
     */
    public function buildPayload(Email $email): array
    {
        $payload = [];

        // Sender
        $sender = $this->formatSender($email);
        if ($sender) {
            $payload['sender'] = $sender;
        }

        // Recipients (To)
        $to = $this->formatAddresses($email->getTo());
        if (! empty($to)) {
            $payload['to'] = $to;
        }

        // Subject
        $payload['subject'] = $email->getSubject() ?? '';

        // HTML & Plaintext body
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        if ($html !== null && $html !== '') {
            $payload['htmlContent'] = is_resource($html) ? stream_get_contents($html) : (string) $html;
        }

        if ($text !== null && $text !== '') {
            $payload['textContent'] = is_resource($text) ? stream_get_contents($text) : (string) $text;
        }

        // Fallback: Brevo requires at least htmlContent or textContent
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = '';
        }

        // Reply-To
        $replyTo = $email->getReplyTo();
        if (! empty($replyTo)) {
            $formattedReplyTo = $this->formatAddress($replyTo[0]);
            if ($formattedReplyTo) {
                $payload['replyTo'] = $formattedReplyTo;
            }
        }

        // CC
        $cc = $this->formatAddresses($email->getCc());
        if (! empty($cc)) {
            $payload['cc'] = $cc;
        }

        // BCC
        $bcc = $this->formatAddresses($email->getBcc());
        if (! empty($bcc)) {
            $payload['bcc'] = $bcc;
        }

        // Attachments
        $attachments = $email->getAttachments();
        if (! empty($attachments)) {
            $payload['attachment'] = [];
            foreach ($attachments as $att) {
                $payload['attachment'][] = [
                    'name' => $att->getFilename() ?: 'attachment',
                    'content' => base64_encode($att->getBody()),
                ];
            }
        }

        return $payload;
    }

    /**
     * Determine and format the sender address.
     *
     * @return array{email: string, name?: string}|null
     */
    protected function formatSender(Email $email): ?array
    {
        if ($sender = $email->getSender()) {
            return $this->formatAddress($sender);
        }

        $from = $email->getFrom();
        if (! empty($from)) {
            return $this->formatAddress($from[0]);
        }

        $fallbackAddress = config('mail.from.address');
        if ($fallbackAddress) {
            $fallback = ['email' => $fallbackAddress];
            if ($name = config('mail.from.name')) {
                $fallback['name'] = $name;
            }
            return $fallback;
        }

        return null;
    }

    /**
     * Format an array of Symfony Address objects into Brevo recipient structures.
     *
     * @param Address[] $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    protected function formatAddresses(array $addresses): array
    {
        $result = [];
        foreach ($addresses as $address) {
            if ($formatted = $this->formatAddress($address)) {
                $result[] = $formatted;
            }
        }
        return $result;
    }

    /**
     * Format an individual Symfony Address.
     *
     * @return array{email: string, name?: string}|null
     */
    protected function formatAddress(Address $address): ?array
    {
        $email = $address->getAddress();
        if (empty($email)) {
            return null;
        }

        $data = ['email' => $email];
        $name = $address->getName();
        if (! empty($name)) {
            $data['name'] = $name;
        }

        return $data;
    }

    public function __toString(): string
    {
        return 'brevo_api';
    }
}

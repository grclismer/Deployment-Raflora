<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingAttachmentService
{
    public const ALLOWED_CATEGORIES = [
        'payment_proof',
        'inspiration_reference',
        'proposal_quotation',
        'event_venue',
        'other_booking_document',
    ];

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public const MAX_SIZE_KB = 5120; // 5MB

    /**
     * Get validation rules for attachment fields to be used in controllers.
     */
    public static function getValidationRules(bool $isRequired = false): array
    {
        return [
            'attachment' => [
                $isRequired ? 'required' : 'nullable',
                'file',
                'mimetypes:' . implode(',', self::ALLOWED_MIMES),
                'mimes:jpeg,jpg,png,webp,pdf',
                'max:' . self::MAX_SIZE_KB,
            ],
            'attachment_category' => [
                'required_with:attachment',
                'nullable',
                'string',
                'in:' . implode(',', self::ALLOWED_CATEGORIES),
            ],
        ];
    }

    /**
     * Store a message with an optional attachment securely using DB transactions.
     * Cleans up the uploaded file if the DB transaction fails.
     */
    public function storeMessage(Booking $booking, array $messageData, ?UploadedFile $file, ?string $category): BookingMessage
    {
        if ($file && !in_array($category, self::ALLOWED_CATEGORIES, true)) {
            throw new \InvalidArgumentException('Invalid or missing attachment category.');
        }

        $attachmentPath = null;
        $attachmentName = null;
        $mimeType = null;
        $fileSize = null;

        if ($file) {
            // Generate a safe unique filename to avoid path traversal or malicious extensions
            $safeFilename = Str::uuid()->toString() . '.' . $file->guessExtension();
            $directory = "messages/attachments/{$booking->id}";
            
            // Note: The file is physically moved to the disk here
            $attachmentPath = $file->storeAs($directory, $safeFilename, 'local');
            
            if (!$attachmentPath) {
                throw new \RuntimeException('Failed to store attachment.');
            }

            $attachmentName = strip_tags(basename($file->getClientOriginalName()));
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
        }

        try {
            $message = DB::transaction(function () use ($messageData, $attachmentPath, $attachmentName, $category, $mimeType, $fileSize) {
                $data = array_merge($messageData, [
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachmentName,
                    'attachment_category' => $attachmentPath ? $category : null,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);

                return BookingMessage::create($data);
            });

            return $message;
        } catch (\Throwable $e) {
            // Clean up the uploaded file if DB insertion fails
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }
            throw $e;
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReturnItemEvidence;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureFileController extends Controller
{
    /**
     * Display the specified evidence file securely.
     */
    public function showEvidence(Request $request, $id)
    {
        $evidence = ReturnItemEvidence::findOrFail($id);
        
        $user = Auth::user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException();
        }

        $booking = $evidence->returnItem->assetReturn->booking;

        // Admin can view all
        if ($user->role === 'admin') {
            // allowed
        } elseif ($user->role === 'client') {
            // Only owning client can view
            $client = $user->client;
            $isOwner = ($client && (int) $booking->client_id === (int) $client->id)
                || ($booking->client && strtolower($booking->client->email) === strtolower($user->email));
            if (!$isOwner) {
                abort(403, 'Unauthorized.');
            }
        } elseif ($user->role === 'staff') {
            // Staff assigned to event can view
            if ((int) $booking->staff_id !== (int) $user->id) {
                abort(403, 'Unauthorized.');
            }
        } else {
            abort(403, 'Unauthorized.');
        }

        if (!Storage::disk('local')->exists($evidence->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response($evidence->file_path, $evidence->file_name, [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => 'inline; filename="' . $evidence->file_name . '"',
        ]);
    }

    /**
     * Display the specified inspiration image securely.
     */
    public function showInspirationImage(Request $request, $bookingId)
    {
        $booking = \App\Models\Booking::findOrFail($bookingId);

        if (!$booking->inspiration_image) {
            abort(404, 'File not found.');
        }

        $user = Auth::user();
        $isAuthorized = false;

        if ($user) {
            if ($user->role === 'admin') {
                $isAuthorized = true;
            } elseif ($user->role === 'client') {
                $client = $user->client;
                $isAuthorized = ($client && (int) $booking->client_id === (int) $client->id)
                    || ($booking->client && strtolower($booking->client->email) === strtolower($user->email));
            } elseif ($user->role === 'staff') {
                $isAuthorized = ((int) $booking->staff_id === (int) $user->id);
            }
        } elseif ($this->guestTokenGrantsAccess($request, $booking)) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            abort(403, 'Unauthorized.');
        }

        $imagePath = null;
        $imageIndex = $request->query('image_index');
        if ($imageIndex !== null && is_array($booking->ai_analysis_data) && !empty($booking->ai_analysis_data['images'])) {
            $images = $booking->ai_analysis_data['images'];
            if (isset($images[$imageIndex]['image_path'])) {
                $imagePath = $images[$imageIndex]['image_path'];
            }
        }

        if (!$imagePath) {
            $imagePath = $booking->inspiration_image;
        }

        if (!Storage::disk('local')->exists($imagePath)) {
            abort(404, 'File not found on disk.');
        }

        $mime = Storage::disk('local')->mimeType($imagePath);
        $filename = basename($imagePath);

        return Storage::disk('local')->response($imagePath, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
    /**
     * Guest-token access applies only to unclaimed guest bookings and requires a non-empty
     * token that matches the stored token. Client-created bookings have no guest token, so an
     * empty or missing query value must never match (ConvertEmptyStringsToNull turns "" into null).
     */
    private function guestTokenGrantsAccess(Request $request, \App\Models\Booking $booking): bool
    {
        $submitted = $request->query('guest_token');
        $stored = $booking->guest_access_token;

        return is_null($booking->client_id)
            && is_string($submitted) && $submitted !== ''
            && is_string($stored) && $stored !== ''
            && hash_equals($stored, $submitted);
    }

    public function showMessageAttachment(Request $request, $id)
    {
        $message = \App\Models\BookingMessage::findOrFail($id);
        
        if (!$message->attachment_path) {
            abort(404, 'No attachment found for this message.');
        }

        $booking = $message->booking;
        $user = Auth::user();
        $isAuthorized = false;

        if ($user) {
            if ($user->role === 'admin') {
                $isAuthorized = true;
            } elseif ($user->role === 'client') {
                $client = $user->client;
                $isAuthorized = (($client && (int) $booking->client_id === (int) $client->id)
                    || ($booking->client && strtolower($booking->client->email) === strtolower($user->email)))
                    && in_array($message->visibility, ['client_admin', 'shared']);
            } elseif ($user->role === 'staff') {
                $isAuthorized = ((int) $booking->staff_id === (int) $user->id)
                    && in_array($message->visibility, ['admin_staff', 'shared']);
            }
        } elseif ($this->guestTokenGrantsAccess($request, $booking)) {
            $isAuthorized = in_array($message->visibility, ['client_admin', 'shared']);
        }

        if (!$isAuthorized) {
            abort(403, 'Unauthorized.');
        }

        if (!Storage::disk('local')->exists($message->attachment_path)) {
            abort(404, 'File not found on disk.');
        }

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->mime_type,
            'Content-Disposition' => 'inline; filename="' . $message->attachment_name . '"',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AdminAlert;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * README Client Workflow: the client and Raflora can meet about a claimed booking.
 * Clients request meetings; Raflora (Admin) schedules, completes, or cancels them.
 */
class BookingMeetingController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeOwner($booking);

        if (!Meeting::bookingAllowsMeetings($booking)) {
            return redirect()->back()->with('error', 'Meetings can no longer be requested for this booking.');
        }

        if ($booking->meetings()->where('status', Meeting::STATUS_REQUESTED)->exists()) {
            return redirect()->back()->with('error', 'You already have a meeting request awaiting Raflora confirmation.');
        }

        $validated = $request->validate([
            'meeting_type' => ['required', 'string', 'in:' . implode(',', array_keys(Meeting::TYPES))],
            'preferred_datetime' => ['required', 'date', 'after:now'],
            'agenda' => ['nullable', 'string', 'max:1000'],
        ], [
            'preferred_datetime.after' => 'Please choose a future date and time for the meeting.',
        ]);

        $meeting = Meeting::create([
            'booking_id' => $booking->id,
            'meeting_type' => $validated['meeting_type'],
            'scheduled_datetime' => Carbon::parse($validated['preferred_datetime']),
            'agenda' => $validated['agenda'] ?? null,
            'status' => Meeting::STATUS_REQUESTED,
            'requested_by' => Auth::id(),
        ]);

        AdminAlert::create([
            'type' => 'meeting_requested',
            'booking_id' => $booking->id,
            'title' => 'Meeting Requested: Booking #' . $booking->id,
            'message' => sprintf(
                'The client requested a %s on %s. Confirm or reschedule it from the booking Communication tab.',
                strtolower($meeting->type_label),
                $meeting->scheduled_datetime->format('M j, Y g:i A')
            ),
            'is_read' => false,
        ]);

        $this->audit($booking, 'meeting_requested', 'Client requested meeting #' . $meeting->id);

        return redirect()->back()->with('success', 'Meeting request sent. Raflora will confirm the schedule with you.');
    }

    public function cancel(Booking $booking, Meeting $meeting): RedirectResponse
    {
        $this->authorizeOwner($booking);

        if ((int) $meeting->booking_id !== (int) $booking->id) {
            abort(404);
        }

        if (!$meeting->isOpen()) {
            return redirect()->back()->with('error', 'This meeting can no longer be cancelled.');
        }

        $meeting->update([
            'status' => Meeting::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        AdminAlert::create([
            'type' => 'meeting_cancelled',
            'booking_id' => $booking->id,
            'title' => 'Meeting Cancelled by Client: Booking #' . $booking->id,
            'message' => 'The client cancelled the ' . strtolower($meeting->type_label) . ' set for ' . $meeting->scheduled_datetime->format('M j, Y g:i A') . '.',
            'is_read' => false,
        ]);

        $this->audit($booking, 'meeting_cancelled', 'Client cancelled meeting #' . $meeting->id);

        return redirect()->back()->with('success', 'Meeting cancelled. Raflora has been notified.');
    }

    private function authorizeOwner(Booking $booking): void
    {
        $user = Auth::user();
        $client = $user ? Client::where('email', $user->email)->first() : null;

        if (!$client || (int) $booking->client_id !== (int) $client->id) {
            abort(403, 'Unauthorized access to this booking.');
        }
    }

    private function audit(Booking $booking, string $action, string $details): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => 'booking',
            'event_type' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
        ]);
    }
}

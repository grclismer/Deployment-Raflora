<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAlert;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ClientNotification;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * README Client Workflow: Raflora (Admin) schedules, confirms, completes, or cancels
 * client meetings for a claimed booking.
 */
class BookingMeetingController extends Controller
{
    /**
     * Schedule a new meeting directly (status: scheduled).
     */
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeAdmin();

        if (!Meeting::bookingAllowsMeetings($booking)) {
            return redirect()->back()->with('error', 'Meetings can only be scheduled for claimed bookings that are still active.');
        }

        $validated = $this->validateSchedule($request);
        $start = Carbon::parse($validated['scheduled_datetime']);

        if ($conflict = Meeting::conflictingScheduledMeeting($start)) {
            return redirect()->back()->withInput()->with('error', $this->conflictMessage($conflict));
        }

        $meeting = Meeting::create([
            'booking_id' => $booking->id,
            'meeting_type' => $validated['meeting_type'],
            'scheduled_datetime' => $start,
            'meeting_link' => $validated['meeting_type'] === 'online' ? $validated['meeting_link'] : null,
            'address' => $validated['meeting_type'] === 'in_person' ? $validated['address'] : null,
            'agenda' => $validated['agenda'] ?? null,
            'status' => Meeting::STATUS_SCHEDULED,
            'scheduled_by' => Auth::id(),
        ]);

        $this->audit($booking, 'meeting_scheduled', 'Admin scheduled meeting #' . $meeting->id);
        $this->notifyClient($booking, 'Meeting Scheduled', $this->scheduleMessage($meeting));

        return redirect()->back()->with('success', 'Meeting scheduled and the client has been notified.');
    }

    /**
     * Confirm (and optionally reschedule) a client-requested meeting.
     */
    public function confirm(Request $request, Booking $booking, Meeting $meeting): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureBelongsTo($booking, $meeting);

        if ($meeting->status !== Meeting::STATUS_REQUESTED) {
            return redirect()->back()->with('error', 'Only a requested meeting can be confirmed.');
        }

        if (!Meeting::bookingAllowsMeetings($booking)) {
            return redirect()->back()->with('error', 'Meetings can only be scheduled for claimed bookings that are still active.');
        }

        $validated = $this->validateSchedule($request);
        $start = Carbon::parse($validated['scheduled_datetime']);

        if ($conflict = Meeting::conflictingScheduledMeeting($start, $meeting->id)) {
            return redirect()->back()->withInput()->with('error', $this->conflictMessage($conflict));
        }

        $meeting->update([
            'meeting_type' => $validated['meeting_type'],
            'scheduled_datetime' => $start,
            'meeting_link' => $validated['meeting_type'] === 'online' ? $validated['meeting_link'] : null,
            'address' => $validated['meeting_type'] === 'in_person' ? $validated['address'] : null,
            'agenda' => $validated['agenda'] ?? $meeting->agenda,
            'status' => Meeting::STATUS_SCHEDULED,
            'scheduled_by' => Auth::id(),
        ]);

        AdminAlert::where('type', 'meeting_requested')
            ->where('booking_id', $booking->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $this->audit($booking, 'meeting_confirmed', 'Admin confirmed meeting #' . $meeting->id);
        $this->notifyClient($booking, 'Meeting Confirmed', $this->scheduleMessage($meeting));

        return redirect()->back()->with('success', 'Meeting confirmed and the client has been notified.');
    }

    public function complete(Request $request, Booking $booking, Meeting $meeting): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureBelongsTo($booking, $meeting);

        if ($meeting->status !== Meeting::STATUS_SCHEDULED) {
            return redirect()->back()->with('error', 'Only a scheduled meeting can be marked as completed.');
        }

        if ($meeting->scheduled_datetime->isFuture()) {
            return redirect()->back()->with('error', 'A meeting cannot be marked as completed before its scheduled time.');
        }

        $validated = $request->validate([
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $meeting->update([
            'status' => Meeting::STATUS_COMPLETED,
            'completed_at' => now(),
            'outcome_notes' => $validated['outcome_notes'] ?? null,
        ]);

        $this->audit($booking, 'meeting_completed', 'Admin marked meeting #' . $meeting->id . ' as completed');

        return redirect()->back()->with('success', 'Meeting marked as completed.');
    }

    public function cancel(Request $request, Booking $booking, Meeting $meeting): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureBelongsTo($booking, $meeting);

        if (!$meeting->isOpen()) {
            return redirect()->back()->with('error', 'This meeting can no longer be cancelled.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $meeting->update([
            'status' => Meeting::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'outcome_notes' => $validated['reason'] ?? $meeting->outcome_notes,
        ]);

        AdminAlert::where('type', 'meeting_requested')
            ->where('booking_id', $booking->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $this->audit($booking, 'meeting_cancelled', 'Admin cancelled meeting #' . $meeting->id);
        $this->notifyClient(
            $booking,
            'Meeting Cancelled',
            'Raflora cancelled the ' . strtolower($meeting->type_label) . ' set for ' . $meeting->scheduled_datetime->format('M j, Y g:i A') . '.'
                . (!empty($validated['reason']) ? ' Reason: ' . $validated['reason'] : '')
        );

        return redirect()->back()->with('success', 'Meeting cancelled and the client has been notified.');
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'meeting_type' => ['required', 'string', 'in:' . implode(',', array_keys(Meeting::TYPES))],
            'scheduled_datetime' => ['required', 'date', 'after:now'],
            'meeting_link' => ['nullable', 'required_if:meeting_type,online', 'url:http,https', 'max:500'],
            'address' => ['nullable', 'required_if:meeting_type,in_person', 'string', 'max:500'],
            'agenda' => ['nullable', 'string', 'max:1000'],
        ], [
            'scheduled_datetime.after' => 'Please choose a future date and time for the meeting.',
            'meeting_link.required_if' => 'An online meeting needs a meeting link.',
            'address.required_if' => 'An in-person meeting needs an address.',
        ]);
    }

    private function conflictMessage(Meeting $conflict): string
    {
        return sprintf(
            'This time overlaps another scheduled meeting (Booking #%d at %s). Meetings occupy a %d-minute slot.',
            $conflict->booking_id,
            $conflict->scheduled_datetime->format('M j, Y g:i A'),
            Meeting::SLOT_MINUTES
        );
    }

    private function scheduleMessage(Meeting $meeting): string
    {
        $where = $meeting->meeting_type === 'online'
            ? 'Join online: ' . $meeting->meeting_link
            : 'Location: ' . $meeting->address;

        return sprintf(
            'Your %s with Raflora is set for %s. %s',
            strtolower($meeting->type_label),
            $meeting->scheduled_datetime->format('M j, Y g:i A'),
            $where
        );
    }

    private function authorizeAdmin(): void
    {
        if (Auth::user()?->role !== 'admin') {
            abort(403, 'Only an authorized admin can manage booking meetings.');
        }
    }

    private function ensureBelongsTo(Booking $booking, Meeting $meeting): void
    {
        if ((int) $meeting->booking_id !== (int) $booking->id) {
            abort(404);
        }
    }

    private function notifyClient(Booking $booking, string $title, string $message): void
    {
        $email = $booking->client?->email;
        $user = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            return;
        }

        ClientNotification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'type' => 'booking_update',
            'title' => $title,
            'message' => $message,
            'is_read' => false,
        ]);
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

<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClientNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientNotificationController extends Controller
{
    /** The inbox shows the most recent updates; older ones stay on each booking's Updates page. */
    public const RECENT_LIMIT = 100;

    public function index(): View
    {
        $mine = ClientNotification::where('user_id', Auth::id());
        $totalCount = (clone $mine)->count();
        $unreadCount = (clone $mine)->where('is_read', false)->count();
        $notifications = $mine
            ->with('booking')
            ->latest()
            ->take(self::RECENT_LIMIT)
            ->get();

        $notificationData = $notifications->map(function (ClientNotification $notification) {
            $parsed = \App\Models\Booking::parseUpdateMessage($notification->message);
            $badgeLabel = 'Update';

            if (!empty($parsed['removed_items'])) {
                $badgeLabel = 'Item Adjustment';
            } elseif (!empty($parsed['items'])) {
                $badgeLabel = 'Item Adjustment';
            } elseif (!empty($parsed['price_changes'])) {
                $badgeLabel = 'Price Change';
            } elseif (str_contains(strtolower($notification->title), 'declined')) {
                $badgeLabel = 'Declined';
            } elseif (str_contains(strtolower($notification->title), 'accepted') || str_contains(strtolower($notification->title), 'approved')) {
                $badgeLabel = 'Approved';
            }

            $summary = trim((string) $notification->message);
            if (!empty($parsed['custom_note'])) {
                $summary = $parsed['custom_note'];
            } elseif (!empty($parsed['items'])) {
                $summary = implode(' • ', array_slice($parsed['items'], 0, 2));
            } elseif (!empty($parsed['price_changes'])) {
                $summary = implode(' • ', array_slice($parsed['price_changes'], 0, 2));
            }

            return [
                'id' => $notification->id,
                'booking_id' => $notification->booking_id,
                'title' => $notification->title,
                'booking_event' => $notification->booking?->event_type ?? 'Booking',
                'timestamp' => $notification->created_at->format('M d, Y • h:i A'),
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $notification->created_at->diffForHumans(),
                'event_date' => $notification->booking?->event_date ? $notification->booking->event_date->format('M d, Y') : null,
                'is_read' => (bool) $notification->is_read,
                'badge' => $badgeLabel,
                'summary' => $summary,
                'details' => $parsed,
            ];
        })->values()->all();

        return view('client.notifications', [
            'notifications' => $notifications,
            'notificationData' => $notificationData,
            'unreadCount' => $unreadCount,
            'olderCount' => max(0, $totalCount - $notifications->count()),
        ]);
    }

    public function bookingUpdates(Booking $booking): View
    {
        $user = Auth::user();
        $client = \App\Models\Client::where('email', $user?->email)->first();

        if ($user?->role !== 'admin' && (! $client || $booking->client_id !== $client->id)) {
            abort(403);
        }

        $notifications = ClientNotification::where('user_id', Auth::id())
            ->where('booking_id', $booking->id)
            ->latest()
            ->get();

        return view('client.booking-updates', [
            'booking' => $booking,
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(ClientNotification $notification): RedirectResponse
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAsReadAjax(ClientNotification $notification): \Illuminate\Http\JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        $unreadCount = ClientNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'is_read' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        ClientNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }
}

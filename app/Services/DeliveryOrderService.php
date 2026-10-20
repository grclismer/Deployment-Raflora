<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Delivery orders (Booking::isDelivery()) are sold, not rented. After dispatch Admin marks the
 * order out for delivery (event_in_progress); the order closes when the client has the items,
 * confirmed by the client in the portal or by Admin when the client confirms by message or call.
 * There is no Material Return or Inventory Reconciliation: dispatched stock stays deducted.
 */
class DeliveryOrderService
{
    public const VIA_CLIENT = 'client';
    public const VIA_ADMIN = 'admin';

    public function canMarkReceived(Booking $booking): bool
    {
        return $booking->isDelivery() && !$booking->isDeliveryReceived() && $booking->status === 'event_in_progress';
    }

    /**
     * Record that the client received the delivery. Returns the booking's new status:
     * completed when fully paid, otherwise event_completed until the balance is settled.
     *
     * @throws RuntimeException when the booking is not an out-for-delivery order
     */
    public function markReceived(Booking $booking, ?int $userId, string $via): string
    {
        return DB::transaction(function () use ($booking, $userId, $via): string {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (!$this->canMarkReceived($locked)) {
                throw new RuntimeException($locked->isDeliveryReceived()
                    ? 'This delivery has already been marked as received.'
                    : 'Only a delivery order that is out for delivery can be marked as received.');
            }

            // Reserved units that never left the warehouse go back to stock, as when an event ends.
            app(InventoryDispatchService::class)->releaseUnfulfilledReservations(
                $locked,
                $userId ?? $locked->handled_by,
                'Released unfulfilled reservation upon delivery receipt for booking #' . $locked->id
            );

            $locked->delivery_received_at = now();
            $locked->delivery_received_by = $userId;
            $locked->delivery_received_via = $via;
            $locked->setNormalizedStatus($this->settledStatus($locked));
            $locked->save();

            AuditLog::record(
                $userId,
                'delivery_received',
                'Delivery for booking #' . $locked->id . ' marked as received by the '
                    . ($via === self::VIA_CLIENT ? 'client' : 'Admin on the client\'s behalf') . '.',
                'booking',
                ['booking_id' => $locked->id, 'via' => $via, 'status' => $locked->status]
            );

            $booking->setRawAttributes($locked->getAttributes(), true);

            return $locked->status;
        });
    }

    /**
     * Status a delivery order takes after a payment is applied: unchanged until the items are
     * received, then completed once nothing is owed (no return audit to wait for).
     */
    public function statusAfterPayment(Booking $booking, string $currentStatus): string
    {
        return $booking->isDeliveryReceived() ? $this->settledStatus($booking) : $currentStatus;
    }

    private function settledStatus(Booking $booking): string
    {
        return (float) $booking->remaining_balance <= 0 ? 'completed' : 'event_completed';
    }
}

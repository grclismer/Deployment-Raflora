<?php

namespace Tests;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Record a verified downpayment so a booking may legitimately move to a confirmed status
     * (Stabilization Phase 3: a booking is only confirmed after a payment has been verified).
     */
    protected function recordVerifiedPayment(Booking $booking, float $amount = 1000.00): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => $amount,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'VERIFIED-' . $booking->id . '-' . uniqid(),
            'status' => 'downpayment_received',
            'verified_at' => now(),
        ]);
    }
}

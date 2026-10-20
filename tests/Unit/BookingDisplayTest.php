<?php

namespace Tests\Unit;

use App\Models\Booking;
use Tests\TestCase;

class BookingDisplayTest extends TestCase
{
    public function test_client_status_label_for_downpayment_receipt_is_readable()
    {
        $booking = new Booking();
        $booking->status = 'downpayment_received';

        $this->assertSame('Downpayment Received', $booking->client_status_label);
    }

    public function test_status_display_label_for_downpayment_receipt_is_human_readable()
    {
        $booking = new Booking();
        $booking->status = 'downpayment_received';

        $this->assertSame('Downpayment Received', $booking->status_display_label);
    }

    public function test_setup_tag_label_is_derived_from_setup_type()
    {
        $booking = new Booking();
        $booking->setup_type = 'on_site';

        $this->assertSame('On-Site Setup', $booking->setup_tag_label);
    }
}

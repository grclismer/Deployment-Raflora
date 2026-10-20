<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Booking;
use App\Models\AdminAlert;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The alerts:check-inventory closure has been replaced by the CheckTieredInventoryShortages class-based command.

/*
|--------------------------------------------------------------------------
| Alert: Flag Expired Quotations (Price Volatility)
|--------------------------------------------------------------------------
| Runs daily. For every booking with status 'quotation_sent' whose
| price_valid_until date has passed, create an AdminAlert to notify
| the admin that the quotation must be re-issued before the client
| can approve.
*/
Artisan::command('alerts:check-expired-quotations', function () {
    $this->info('Checking for expired quotations...');

    $expiredBookings = Booking::where('status', 'quotation_sent')
        ->whereNotNull('price_valid_until')
        ->whereDate('price_valid_until', '<', Carbon::today())
        ->get();

    $alertsCreated = 0;

    foreach ($expiredBookings as $booking) {
        $exists = AdminAlert::where('type', 'quotation_expired')
            ->where('booking_id', $booking->id)
            ->where('is_read', false)
            ->exists();

        if (!$exists) {
            AdminAlert::create([
                'type'       => 'quotation_expired',
                'title'      => 'Quotation Expired: Booking #' . $booking->id,
                'message'    => sprintf(
                    'The quotation for Booking #%d (%s, %s) expired on %s due to floral price volatility. Please review current material costs and re-issue the quotation before the client can approve.',
                    $booking->id,
                    ucfirst($booking->event_type),
                    optional($booking->client)->full_name ?? ($booking->guest_name ?? 'Guest'),
                    $booking->price_valid_until->format('M d, Y')
                ),
                'booking_id' => $booking->id,
                'is_read'    => false,
            ]);
            $alertsCreated++;
        }
    }

    $this->info("Done. {$alertsCreated} new expiration alert(s) created.");
})->purpose('Flag quotation_sent bookings whose price validity window has lapsed');

/*
|--------------------------------------------------------------------------
| Alert: Check Price Reconfirmations (Long-Term Booking Volatility)
|--------------------------------------------------------------------------
| Runs daily. For long-term bookings with tentative pricing whose event date
| is within the configured reconfirmation threshold (default 30 days), create
| an AdminAlert notifying Admin that pricing and availability must be reconfirmed.
*/
Artisan::command('alerts:check-price-reconfirmations', function () {
    $this->info('Checking for long-term bookings requiring price reconfirmation...');

    $thresholdDays = \App\Models\Setting::getPriceReconfirmationThresholdDays();
    $today = Carbon::today();
    $cutoffDate = $today->copy()->addDays($thresholdDays);

    $eligibleBookings = Booking::query()
        ->whereNotIn('status', ['declined', 'cancelled', 'completed'])
        ->whereNotNull('event_date')
        ->whereDate('event_date', '>=', $today)
        ->whereDate('event_date', '<=', $cutoffDate)
        ->whereHas('quotations', function ($q) {
            $q->where('is_tentative', true)
              ->whereNull('reconfirmed_at')
              ->whereIn('status', [\App\Models\Quotation::STATUS_ACCEPTED, \App\Models\Quotation::STATUS_ISSUED]);
        })
        ->get();

    $alertsCreated = 0;

    foreach ($eligibleBookings as $booking) {
        $exists = AdminAlert::where('type', 'price_reconfirmation_due')
            ->where('booking_id', $booking->id)
            ->where('is_read', false)
            ->exists();

        if (!$exists) {
            AdminAlert::create([
                'type'       => 'price_reconfirmation_due',
                'title'      => 'Price Reconfirmation Due: Booking #' . $booking->id,
                'message'    => sprintf(
                    'Price reconfirmation is due for Booking #%d (%s, %s) scheduled on %s. Review current material pricing and availability before the event.',
                    $booking->id,
                    ucfirst($booking->event_type),
                    optional($booking->client)->full_name ?? ($booking->guest_name ?? 'Guest'),
                    $booking->event_date->format('M d, Y')
                ),
                'booking_id' => $booking->id,
                'is_read'    => false,
            ]);
            $alertsCreated++;
        }
    }

    $this->info("Done. {$alertsCreated} new price reconfirmation alert(s) created.");
})->purpose('Flag bookings with tentative pricing within reconfirmation threshold');

/*
|--------------------------------------------------------------------------
| Laravel Scheduler: Run alert checks daily at 6:00 AM
|--------------------------------------------------------------------------
*/
Schedule::command('inventory:check-tiered-shortages')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('alerts:check-expired-quotations')->dailyAt('06:05')->withoutOverlapping()->onOneServer();
Schedule::command('alerts:check-price-reconfirmations')->dailyAt('06:10')->withoutOverlapping()->onOneServer();
Schedule::command('guest-bookings:cleanup')->hourly()->withoutOverlapping()->onOneServer();

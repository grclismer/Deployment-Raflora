<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event details the Smart AI analysis uses, kept with the booking so the florist sees what
 * the AI saw: end time (the guest form already asked for it but never saved it), whether the
 * venue is indoor/outdoor, and the optional design brief (App\Support\EventDesignBrief).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'temporary_guest_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->time('end_time')->nullable()->after('event_time');
                $table->string('venue_setting', 20)->nullable()->after('venue_longitude');
                $table->json('design_brief')->nullable()->after('special_requests');
            });
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'temporary_guest_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['end_time', 'venue_setting', 'design_brief']);
            });
        }
    }
};

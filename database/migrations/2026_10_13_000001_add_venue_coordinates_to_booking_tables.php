<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Map coordinates for the event venue.
 *
 * The location finder on the booking forms lets the client pin the exact venue. The free-text
 * `venue` column stays the source of truth for the address; these columns only hold the pin so
 * Admin and Staff can open the venue on a map and get directions. Both stay null when no pin is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'temporary_guest_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('venue_latitude', 10, 7)->nullable()->after('venue');
                $table->decimal('venue_longitude', 10, 7)->nullable()->after('venue_latitude');
            });
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'temporary_guest_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['venue_latitude', 'venue_longitude']);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe data correction: reclassify all legacy Staff messages that were improperly 
        // defaulted to 'client_admin' during the column creation, back to their true historical 
        // audience equivalent, which is 'shared' (visible to Client, Admin, and assigned Staff).
        DB::table('booking_messages')
            ->where('sender_type', 'staff')
            ->where('visibility', 'client_admin')
            ->update(['visibility' => 'shared']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // INTENTIONAL NO-OP:
        // Reverting this migration by blindly setting 'shared' staff messages back to 'client_admin'
        // would incorrectly corrupt valid, newly-created Staff messages that were intentionally 
        // submitted as 'shared' after Phase 3.9C5. 
        // Since we cannot reliably distinguish between a legacy 'shared' message and a new 'shared' message 
        // without a known timestamp boundary, this data correction is one-way.
    }
};

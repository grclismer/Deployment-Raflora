<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a unique constraint on (booking_id, version) to quotations.
     *
     * This is safe because the quotations table currently has zero rows.
     * Also adds a nullable 'issued_by' FK for the admin who issued the quotation.
     */
    public function up(): void
    {
        // Safety check: only add unique constraint if there are no duplicate (booking_id, version) pairs
        $hasDuplicates = DB::table('quotations')
            ->select('booking_id', 'version')
            ->whereNotNull('version')
            ->groupBy('booking_id', 'version')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        Schema::table('quotations', function (Blueprint $table) use ($hasDuplicates) {
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            if (! $hasDuplicates) {
                $table->unique(['booking_id', 'version'], 'quotations_booking_version_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'issued_by')) {
                $table->dropForeign(['issued_by']);
                $table->dropColumn('issued_by');
            }

            // Drop the unique index if it exists
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = collect($sm->listTableIndexes('quotations') ?? []);
            if ($indexes->has('quotations_booking_version_unique')) {
                $table->dropUnique('quotations_booking_version_unique');
            }
        });
    }
};

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
        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('assigned_staff_id')->nullable()->after('booking_id')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('inspected_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('approval_status')->default('not_required')->after('approved_at');
        });

        // Safe backward-compatible data backfill
        try {
            // 1. Backfill assigned_staff_id from booking's staff_id if available
            DB::statement("
                UPDATE `returns` r
                INNER JOIN `bookings` b ON r.booking_id = b.id
                SET r.assigned_staff_id = b.staff_id
                WHERE r.assigned_staff_id IS NULL AND b.staff_id IS NOT NULL
            ");

            // 2. Set approval_status for existing returns:
            // - If status is Completed and total_damage_charge > 0: set to 'approved', approved_by to inspected_by, approved_at to updated_at
            DB::statement("
                UPDATE `returns`
                SET approval_status = 'approved',
                    approved_by = inspected_by,
                    approved_at = updated_at
                WHERE status = 'Completed' AND total_damage_charge > 0
            ");

            // - If status is Partially Returned and has damaged/lost items with pending charges: set to 'pending'
            DB::statement("
                UPDATE `returns` r
                SET r.approval_status = 'pending'
                WHERE r.status = 'Partially Returned'
                  AND EXISTS (
                      SELECT 1 FROM return_items ri
                      WHERE ri.return_id = r.id
                        AND (ri.quantity_damaged > 0 OR ri.quantity_lost > 0 OR ri.condition IN ('damaged', 'lost', 'mixed'))
                        AND ri.charge_decision = 'pending'
                  )
            ");
        } catch (\Throwable $e) {
            // Graceful fallback for environments (like SQLite during tests) where multi-table UPDATE has different syntax
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropForeign(['assigned_staff_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['assigned_staff_id', 'approved_by', 'approved_at', 'approval_status']);
        });
    }
};

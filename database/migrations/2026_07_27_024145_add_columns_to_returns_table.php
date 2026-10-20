<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('booking_id')->after('id')->constrained('bookings')->cascadeOnDelete();
            $table->date('return_date')->nullable()->after('booking_id');
            $table->string('status')->default('Pending')->after('return_date'); // Pending, Partially Returned, Completed
            $table->decimal('total_damage_charge', 12, 2)->default(0)->after('status');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete()->after('total_damage_charge');
            $table->text('notes')->nullable()->after('inspected_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['inspected_by']);
            $table->dropColumn(['booking_id', 'return_date', 'status', 'total_damage_charge', 'inspected_by', 'notes']);
        });
    }
};

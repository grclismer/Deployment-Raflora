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
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50)->nullable();
            $table->date('event_date')->nullable();
            $table->string('venue', 500)->nullable();
            $table->text('special_requests')->nullable();
            $table->string('inspiration_image')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('event_size', 50)->nullable();
            $table->decimal('downpayment_amount', 10, 2)->nullable();
            $table->date('downpayment_date')->nullable();
            $table->decimal('total_quoted', 10, 2)->nullable();
            $table->date('price_valid_until')->nullable();
            $table->date('suggested_procurement_date')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['handled_by']);
            $table->dropColumn([
                'client_id',
                'handled_by',
                'event_type',
                'event_date',
                'venue',
                'special_requests',
                'inspiration_image',
                'status',
                'event_size',
                'downpayment_amount',
                'downpayment_date',
                'total_quoted',
                'price_valid_until',
                'suggested_procurement_date',
                'cancellation_reason',
            ]);
        });
    }
};

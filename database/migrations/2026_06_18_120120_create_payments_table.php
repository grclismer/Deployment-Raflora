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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->string('payment_type', 50);
            $table->string('payment_option', 32)->nullable()->comment('downpayment|full_payment');
            $table->decimal('amount_paid', 12, 2)->default(0.00);
            $table->decimal('remaining_balance', 12, 2)->default(0.00);
            $table->string('reference_number', 255);
            $table->string('status', 50)->default('pending');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

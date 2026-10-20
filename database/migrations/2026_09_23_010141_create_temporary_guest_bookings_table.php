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
        Schema::create('temporary_guest_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('claim_token_hash')->unique();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->text('guest_address');
            $table->string('booking_type')->nullable(); // 'preset' or 'custom_ai'
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('event_type');
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->string('venue', 500);
            $table->integer('table_count')->nullable();
            $table->integer('guest_count')->nullable();
            $table->text('special_requests')->nullable();
            $table->string('inspiration_image_path')->nullable();
            $table->json('analysis_data')->nullable(); // stored AI results
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_guest_bookings');
    }
};

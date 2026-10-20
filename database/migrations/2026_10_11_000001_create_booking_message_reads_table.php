<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user read position for a booking's message thread.
 *
 * booking_messages.read_at is a single shared flag used by the Admin/Client conversation, so it cannot
 * tell whether a particular Staff member has seen a message. This table records, per user and booking,
 * the last time that user opened the thread; anything newer from someone else is unread for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_message_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->timestamp('last_read_at');
            $table->timestamps();
            $table->unique(['user_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_message_reads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client's "Schedule" change request (client Request Changes). Admin checks the date,
 * talks it through with the client, then accepts it (the booking's date/time change) or
 * declines it (App\Services\ScheduleChangeService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_schedule_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_message_id')->nullable()->constrained('booking_messages')->nullOnDelete();
            $table->date('requested_date');
            $table->time('requested_start_time')->nullable();
            $table->time('requested_end_time')->nullable();
            $table->date('previous_date')->nullable();
            $table->string('previous_start_time', 8)->nullable();
            $table->string('previous_end_time', 8)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | superseded
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_schedule_requests');
    }
};

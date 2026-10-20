<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client ↔ Raflora meetings for a booking (README Client Workflow:
     * "the client and admin can now communicate/negotiate/meeting about their booking").
     */
    public function up(): void
    {
        if (Schema::hasTable('meetings')) {
            return;
        }

        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('meeting_type', 20); // online | in_person
            $table->dateTime('scheduled_datetime');
            $table->string('meeting_link', 500)->nullable();
            $table->string('address', 500)->nullable();
            $table->text('agenda')->nullable();
            $table->string('status', 20)->default('requested'); // requested | scheduled | completed | cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};

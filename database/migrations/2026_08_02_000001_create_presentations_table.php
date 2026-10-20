<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('presentations')) {
            Schema::create('presentations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->string('version')->default('v1');
                $table->string('file_path');
                $table->string('file_name');
                $table->timestamp('sent_at')->nullable();
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('sent');
                $table->string('approval_status')->nullable();
                $table->text('feedback_text')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};

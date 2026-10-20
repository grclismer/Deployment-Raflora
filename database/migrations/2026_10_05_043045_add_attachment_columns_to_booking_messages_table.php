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
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_category', 50)->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->integer('file_size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path',
                'attachment_name',
                'attachment_category',
                'mime_type',
                'file_size'
            ]);
        });
    }
};

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
            $table->string('submission_key')->nullable()->unique()->after('related_quotation_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->dropColumn('submission_key');
        });
    }
};

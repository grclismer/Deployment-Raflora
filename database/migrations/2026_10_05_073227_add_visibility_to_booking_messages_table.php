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
            $table->string('visibility', 20)->default('client_admin')->after('message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};

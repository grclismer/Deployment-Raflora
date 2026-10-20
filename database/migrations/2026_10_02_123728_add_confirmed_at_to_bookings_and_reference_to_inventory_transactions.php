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
            $table->timestamp('confirmed_at')->nullable()->after('status');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_transaction_id')->nullable()->after('booking_id');
            $table->foreign('reference_transaction_id')
                  ->references('id')
                  ->on('inventory_transactions')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['reference_transaction_id']);
            $table->dropColumn('reference_transaction_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};

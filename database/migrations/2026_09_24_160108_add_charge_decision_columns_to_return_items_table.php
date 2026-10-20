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
        Schema::table('return_items', function (Blueprint $table) {
            $table->string('charge_decision')->default('pending'); // pending, no_charge, charge
            $table->text('charge_reason')->nullable();
            $table->foreignId('charge_decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('charge_decision_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropForeign(['charge_decision_by']);
            $table->dropColumn([
                'charge_decision',
                'charge_reason',
                'charge_decision_by',
                'charge_decision_at'
            ]);
        });
    }
};

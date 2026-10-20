<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('recorded_by');
            }
            if (!Schema::hasColumn('payments', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('payments', 'payment_option')) {
                $table->string('payment_option', 32)->nullable()->after('payment_type')->comment('downpayment|full_payment');
            }
            if (!Schema::hasColumn('payments', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0.00)->after('amount');
            }
            if (!Schema::hasColumn('payments', 'remaining_balance')) {
                $table->decimal('remaining_balance', 12, 2)->default(0.00)->after('amount_paid');
            }

            if (!Schema::hasColumn('payments', 'verified_by')) {
                $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verified_by', 'verified_at', 'payment_option', 'amount_paid', 'remaining_balance']);
        });
    }
};

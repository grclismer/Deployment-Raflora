<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-01G: Add Admin-configurable downpayment percentage to quotations.
     * The Admin sets this value when issuing a quotation. It is stored
     * immutably on the quotation so that payment calculations remain
     * tied to the offer that was actually accepted by the client.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Admin-configurable downpayment percentage (e.g. 50.00 = 50%)
            $table->decimal('downpayment_percentage', 5, 2)->default(50.00)->after('final_quoted_price');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('downpayment_percentage');
        });
    }
};

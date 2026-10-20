<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery orders (event type "delivery") are sold, not rented: there is no return audit.
 * The booking closes once the client has received the items, confirmed either by the client
 * in the portal or by Admin on the client's behalf (App\Services\DeliveryOrderService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('delivery_received_at')->nullable()->after('preparation_status');
            $table->foreignId('delivery_received_by')->nullable()->after('delivery_received_at')->constrained('users')->nullOnDelete();
            $table->string('delivery_received_via', 20)->nullable()->after('delivery_received_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_received_by');
            $table->dropColumn(['delivery_received_at', 'delivery_received_via']);
        });
    }
};

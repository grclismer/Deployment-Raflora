<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'name')) {
                $table->string('name')->nullable()->index();
            }
            if (!Schema::hasColumn('inventory_items', 'category')) {
                $table->string('category')->nullable();
            }
            if (!Schema::hasColumn('inventory_items', 'is_perishable')) {
                $table->boolean('is_perishable')->default(true);
            }
            if (!Schema::hasColumn('inventory_items', 'current_stock')) {
                $table->decimal('current_stock', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('inventory_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('inventory_items', 'min_stock')) {
                $table->decimal('min_stock', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('inventory_items', 'unit')) {
                $table->string('unit')->nullable()->default('pcs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'name')) $table->dropColumn('name');
            if (Schema::hasColumn('inventory_items', 'category')) $table->dropColumn('category');
            if (Schema::hasColumn('inventory_items', 'is_perishable')) $table->dropColumn('is_perishable');
            if (Schema::hasColumn('inventory_items', 'current_stock')) $table->dropColumn('current_stock');
            if (Schema::hasColumn('inventory_items', 'unit_cost')) $table->dropColumn('unit_cost');
            if (Schema::hasColumn('inventory_items', 'min_stock')) $table->dropColumn('min_stock');
            if (Schema::hasColumn('inventory_items', 'unit')) $table->dropColumn('unit');
        });
    }
};

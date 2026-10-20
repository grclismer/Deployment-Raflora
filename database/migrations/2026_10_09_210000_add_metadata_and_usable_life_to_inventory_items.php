<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('status')->default('active')->index()->after('unit');
            $table->text('description')->nullable()->after('status');
            $table->unsignedInteger('usable_life_value')->nullable()->after('description');
            $table->string('usable_life_unit', 20)->nullable()->default('days')->after('usable_life_value');
            $table->string('supplier_name')->nullable()->after('usable_life_unit');
            $table->string('supplier_contact_person')->nullable()->after('supplier_name');
            $table->string('supplier_contact_number')->nullable()->after('supplier_contact_person');
            $table->string('storage_location')->nullable()->after('supplier_contact_number');
            $table->text('tags')->nullable()->after('storage_location');
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->date('received_date');
            $table->decimal('quantity_received', 12, 2)->default(0);
            $table->decimal('quantity_remaining', 12, 2)->default(0);
            $table->unsignedInteger('usable_life_value')->default(1);
            $table->string('usable_life_unit', 20)->default('days');
            $table->date('usable_until')->index();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('inventory_stock_id')->nullable()->after('inventory_item_id')->constrained('inventory_stocks')->nullOnDelete();
        });

        // Seed initial default stock record for existing items that have current_stock > 0
        $existingItems = DB::table('inventory_items')->get();
        foreach ($existingItems as $item) {
            $lifeValue = $item->is_perishable ? 7 : 3;
            $lifeUnit = $item->is_perishable ? 'days' : 'years';
            $receivedDate = $item->created_at ? substr($item->created_at, 0, 10) : date('Y-m-d');
            $usableUntil = $item->is_perishable 
                ? date('Y-m-d', strtotime($receivedDate . ' +7 days'))
                : date('Y-m-d', strtotime($receivedDate . ' +3 years'));

            DB::table('inventory_items')->where('id', $item->id)->update([
                'status' => 'active',
                'usable_life_value' => $lifeValue,
                'usable_life_unit' => $lifeUnit,
            ]);

            if ((float) $item->current_stock > 0) {
                DB::table('inventory_stocks')->insert([
                    'inventory_item_id' => $item->id,
                    'received_date' => $receivedDate,
                    'quantity_received' => $item->current_stock,
                    'quantity_remaining' => $item->current_stock,
                    'usable_life_value' => $lifeValue,
                    'usable_life_unit' => $lifeUnit,
                    'usable_until' => $usableUntil,
                    'unit_cost' => $item->unit_cost,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($item->image_path) {
                DB::table('inventory_item_images')->insert([
                    'inventory_item_id' => $item->id,
                    'image_path' => $item->image_path,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropForeign(['inventory_stock_id']);
            $table->dropColumn('inventory_stock_id');
        });

        Schema::dropIfExists('inventory_item_images');
        Schema::dropIfExists('inventory_stocks');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'description',
                'usable_life_value',
                'usable_life_unit',
                'supplier_name',
                'supplier_contact_person',
                'supplier_contact_number',
                'storage_location',
                'tags',
            ]);
        });
    }
};

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
        Schema::create('package_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
            $table->string('image_path');
            $table->timestamps();
        });

        // Migrate existing primary images from packages table
        $packages = DB::table('packages')->whereNotNull('image_path')->get();
        foreach ($packages as $package) {
            // Note: we'll prefix with storage/ if gallery logic does it, but we'll stick to how the original stored it.
            // Packages currently store paths like "packages/abc.jpg" and render with asset('storage/' . $path).
            DB::table('package_images')->insert([
                'package_id' => $package->id,
                'image_path' => $package->image_path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_images');
    }
};

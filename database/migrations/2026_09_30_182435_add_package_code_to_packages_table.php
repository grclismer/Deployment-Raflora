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
        Schema::table('packages', function (Blueprint $table) {
            $table->string('package_code', 20)->nullable()->after('id');
        });

        // Backfill existing packages
        $packages = DB::table('packages')->get();
        foreach ($packages as $package) {
            $code = null;
            $newDescription = $package->description;
            
            if ($package->description) {
                // Try to extract "Code: XXX-XXX"
                if (preg_match('/Code:\s*([A-Z0-9\-]+)\b/i', $package->description, $matches)) {
                    $code = $matches[1];
                    // Remove that line from description
                    $newDescription = preg_replace('/Code:\s*[A-Z0-9\-]+\s*[\r\n]*/i', '', $package->description);
                    $newDescription = trim($newDescription);
                }
            }
            
            if (!$code) {
                $code = 'PKG-' . str_pad($package->id, 4, '0', STR_PAD_LEFT);
            }

            DB::table('packages')->where('id', $package->id)->update([
                'package_code' => $code,
                'description' => $newDescription ?: null
            ]);
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->string('package_code', 20)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('package_code');
        });
    }
};

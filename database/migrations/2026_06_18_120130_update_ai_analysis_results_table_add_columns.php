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
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_analysis_results', 'booking_id')) {
                $table->foreignId('booking_id')->after('id')->constrained('bookings')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('ai_analysis_results', 'raw_gemini_response')) {
                $table->json('raw_gemini_response')->nullable()->after('booking_id');
            }
            if (!Schema::hasColumn('ai_analysis_results', 'suggested_materials')) {
                $table->json('suggested_materials')->nullable()->after('raw_gemini_response');
            }
            if (!Schema::hasColumn('ai_analysis_results', 'analyzed_at')) {
                $table->dateTime('analyzed_at')->nullable()->after('suggested_materials');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analysis_results', function (Blueprint $table) {
            if (Schema::hasColumn('ai_analysis_results', 'analyzed_at')) {
                $table->dropColumn('analyzed_at');
            }
            if (Schema::hasColumn('ai_analysis_results', 'suggested_materials')) {
                $table->dropColumn('suggested_materials');
            }
            if (Schema::hasColumn('ai_analysis_results', 'raw_gemini_response')) {
                $table->dropColumn('raw_gemini_response');
            }
            if (Schema::hasColumn('ai_analysis_results', 'booking_id')) {
                $table->dropForeign(['booking_id']);
                $table->dropColumn('booking_id');
            }
        });
    }
};

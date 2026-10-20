<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            if (!Schema::hasColumn('presentations', 'approval_status')) {
                $table->string('approval_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('presentations', 'feedback_text')) {
                $table->text('feedback_text')->nullable()->after('approval_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['version', 'approval_status', 'feedback_text']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'event_type')) {
                $table->string('event_type', 64)->nullable()->after('module');
            }
            if (! Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('details');
            }
            if (! Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
            if (! Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('new_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['event_type', 'old_values', 'new_values', 'ip_address']);
        });
    }
};

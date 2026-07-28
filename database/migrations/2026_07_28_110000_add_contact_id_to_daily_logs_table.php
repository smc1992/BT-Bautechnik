<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_logs', 'contact_id')) {
                $table->uuid('contact_id')->nullable()->after('project_id');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            if (Schema::hasColumn('daily_logs', 'contact_id')) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn('contact_id');
            }
        });
    }
};

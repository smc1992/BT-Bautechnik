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
        // Add indexes for high-frequency queries
        $tables = [
            'projects' => function (Blueprint $table) {
                $table->index(['status', 'year'], 'idx_projects_status_year');
                $table->index('contact_id', 'idx_projects_contact_id');
            },
            'invoices' => function (Blueprint $table) {
                $table->index(['project_id', 'status'], 'idx_invoices_project_status');
                $table->index('invoice_date', 'idx_invoices_date');
                $table->index('invoice_type', 'idx_invoices_type');
            },
            'daily_logs' => function (Blueprint $table) {
                $table->index(['project_id', 'date'], 'idx_daily_logs_proj_date');
            },
            'defects' => function (Blueprint $table) {
                $table->index(['project_id', 'status'], 'idx_defects_proj_status');
            },
            'supplements' => function (Blueprint $table) {
                $table->index(['project_id', 'status'], 'idx_supplements_proj_status');
            },
            'measurements' => function (Blueprint $table) {
                $table->index(['project_id', 'measurement_date'], 'idx_measurements_proj_date');
            },
            'time_entries' => function (Blueprint $table) {
                $table->index(['user_id', 'entry_date'], 'idx_time_entries_user_date');
                $table->index(['project_id', 'status'], 'idx_time_entries_proj_status');
            },
            'project_plans' => function (Blueprint $table) {
                $table->index(['project_id', 'category'], 'idx_plans_proj_cat');
            },
            'equipment' => function (Blueprint $table) {
                $table->index(['current_project_id', 'status'], 'idx_equipment_proj_status');
                $table->index('next_uvv_inspection', 'idx_equipment_uvv');
            },
        ];

        foreach ($tables as $tableName => $callback) {
            try {
                if (Schema::hasTable($tableName)) {
                    Schema::table($tableName, $callback);
                }
            } catch (\Throwable $e) {
                // Ignore if index already exists or not supported by driver
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex('idx_equipment_proj_status');
            $table->dropIndex('idx_equipment_uvv');
        });

        Schema::table('project_plans', function (Blueprint $table) {
            $table->dropIndex('idx_plans_proj_cat');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex('idx_time_entries_user_date');
            $table->dropIndex('idx_time_entries_proj_status');
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->dropIndex('idx_measurements_proj_date');
        });

        Schema::table('supplements', function (Blueprint $table) {
            $table->dropIndex('idx_supplements_proj_status');
        });

        Schema::table('defects', function (Blueprint $table) {
            $table->dropIndex('idx_defects_proj_status');
        });

        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropIndex('idx_daily_logs_proj_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_project_status');
            $table->dropIndex('idx_invoices_date');
            $table->dropIndex('idx_invoices_type');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('idx_projects_status_year');
            $table->dropIndex('idx_projects_contact_id');
        });
    }
};

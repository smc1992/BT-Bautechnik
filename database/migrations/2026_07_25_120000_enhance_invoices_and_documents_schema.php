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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'invoice_type')) {
                $table->string('invoice_type')->default('standard'); // standard, down_payment, final, storno
            }
            if (!Schema::hasColumn('invoices', 'sequence_number')) {
                $table->integer('sequence_number')->default(1);
            }
            if (!Schema::hasColumn('invoices', 'original_invoice_id')) {
                $table->foreignUuid('original_invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            }
            if (!Schema::hasColumn('invoices', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'is_supplement')) {
                $table->boolean('is_supplement')->default(false);
            }
            if (!Schema::hasColumn('invoices', 'supplement_number')) {
                $table->string('supplement_number')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'security_deduction_rate')) {
                $table->decimal('security_deduction_rate', 5, 2)->default(0.00); // e.g. 5% Gewährleistungseinbehalt
            }
        });

        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'is_supplement')) {
                $table->boolean('is_supplement')->default(false);
            }
            if (!Schema::hasColumn('offers', 'supplement_number')) {
                $table->string('supplement_number')->nullable();
            }
        });

        Schema::table('defects', function (Blueprint $table) {
            if (!Schema::hasColumn('defects', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });

        Schema::table('daily_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_logs', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_type', 'sequence_number', 'original_invoice_id', 
                'cancel_reason', 'is_supplement', 'supplement_number', 'security_deduction_rate'
            ]);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['is_supplement', 'supplement_number']);
        });

        Schema::table('defects', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });

        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};

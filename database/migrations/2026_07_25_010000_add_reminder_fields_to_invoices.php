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
            if (!Schema::hasColumn('invoices', 'reminder_level')) {
                $table->integer('reminder_level')->default(0);
            }
            if (!Schema::hasColumn('invoices', 'reminder_date')) {
                $table->date('reminder_date')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'dunning_fee')) {
                $table->decimal('dunning_fee', 10, 2)->default(0.00);
            }
            if (!Schema::hasColumn('invoices', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['reminder_level', 'reminder_date', 'dunning_fee', 'sent_at']);
        });
    }
};

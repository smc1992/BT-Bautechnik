<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add contact_id and document_type to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable()->after('project_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->string('type')->default('invoice')->after('invoice_number'); // invoice (Endrechnung), advance (Abschlagsrechnung), credit_note (Gutschrift)
        });

        // 2. Add contact_id to offers
        Schema::table('offers', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable()->after('project_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
        });

        // 3. Add contact_id to actual_costs
        Schema::table('actual_costs', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable()->after('project_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('actual_costs', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn(['contact_id', 'type']);
        });
    }
};

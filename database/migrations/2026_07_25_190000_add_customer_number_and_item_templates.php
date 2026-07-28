<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add customer_number to contacts table
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'customer_number')) {
                $table->string('customer_number')->nullable()->unique()->after('type');
            }
        });

        // 2. Create invoice_item_templates table for reusable position building blocks
        if (!Schema::hasTable('invoice_item_templates')) {
            Schema::create('invoice_item_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('unit')->default('Stk');
                $table->decimal('unit_price', 10, 2)->default(0.00);
                $table->decimal('vat_rate', 5, 2)->default(19.00);
                $table->string('category')->default('Standard');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_templates');

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'customer_number')) {
                $table->dropColumn('customer_number');
            }
        });
    }
};

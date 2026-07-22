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
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->string('delivery_date')->nullable();
            $table->integer('due_days')->default(14);
            $table->decimal('discount_rate', 5, 2)->default(0.00);
            $table->string('tax_mode')->default('standard'); // standard, reverse, small, custom
            $table->string('tax_reason')->nullable();
            $table->text('custom_payment_note')->nullable();
            $table->text('custom_legal_text')->nullable();
            $table->decimal('total_net', 15, 2)->default(0.00);
            $table->decimal('total_tax', 15, 2)->default(0.00);
            $table->decimal('total_gross', 15, 2)->default(0.00);
            $table->string('status')->default('draft'); // draft, sent, paid, cancelled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

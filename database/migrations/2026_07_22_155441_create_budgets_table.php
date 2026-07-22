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
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
            $table->decimal('material_budget', 15, 2)->default(0.00);
            $table->decimal('wage_budget', 15, 2)->default(0.00);
            $table->decimal('buffer_rate', 5, 2)->default(15.00);
            $table->decimal('buffer_amount', 15, 2)->default(0.00);
            $table->decimal('total_with_buffer', 15, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};

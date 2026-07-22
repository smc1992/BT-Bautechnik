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
        Schema::create('actual_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('type'); // material, subcontractor, internal_wage, other
            $table->string('subcontractor_name')->nullable();
            $table->decimal('cost_amount', 15, 2)->default(0.00);
            $table->string('description');
            $table->date('date');
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actual_costs');
    }
};

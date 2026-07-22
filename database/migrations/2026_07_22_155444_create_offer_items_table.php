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
        Schema::create('offer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('section_id')->constrained('offer_sections')->onDelete('cascade');
            $table->string('pos_number')->nullable();
            $table->text('description');
            $table->decimal('quantity', 15, 4)->default(1.0000);
            $table->string('unit')->default('Stk.');
            $table->decimal('unit_price', 15, 4)->default(0.0000);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_items');
    }
};

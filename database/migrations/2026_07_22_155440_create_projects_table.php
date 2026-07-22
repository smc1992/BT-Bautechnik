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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('zip')->nullable();
            $table->string('city_street')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('work_type')->nullable();
            $table->integer('start_week')->nullable();
            $table->integer('end_week')->nullable();
            $table->string('status')->default('draft'); // draft, active, completed, paused
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

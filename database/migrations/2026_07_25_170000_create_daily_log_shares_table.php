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
        Schema::create('daily_log_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('daily_log_id')->constrained('daily_logs')->onDelete('cascade');
            $table->string('share_token', 64)->unique();
            $table->string('approver_name')->nullable();
            $table->string('approver_role')->default('Architekt'); // Architekt, Bauherr, Bauleiter
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->longText('signature_data')->nullable(); // Base64 Canvas Drawing data
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_log_shares');
    }
};

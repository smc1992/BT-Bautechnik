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
        Schema::create('agent_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('title')->default('Neue Unterhaltung');
            $table->timestamps();
        });

        Schema::create('agent_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_chat_id')->constrained('agent_chats')->onDelete('cascade');
            $table->string('role'); // user, assistant, system
            $table->longText('content');
            $table->json('tools')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_chat_messages');
        Schema::dropIfExists('agent_chats');
    }
};

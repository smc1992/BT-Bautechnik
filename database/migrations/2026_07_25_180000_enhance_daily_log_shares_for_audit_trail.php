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
        Schema::table('daily_log_shares', function (Blueprint $table) {
            $table->string('verification_pin', 6)->nullable()->after('approver_role');
            $table->timestamp('pin_sent_at')->nullable()->after('verification_pin');
            $table->boolean('is_email_verified')->default(false)->after('pin_sent_at');
            $table->string('client_ip', 45)->nullable()->after('signature_data');
            $table->text('user_agent')->nullable()->after('client_ip');
            $table->string('sha256_hash', 64)->nullable()->after('user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_log_shares', function (Blueprint $table) {
            $table->dropColumn([
                'verification_pin',
                'pin_sent_at',
                'is_email_verified',
                'client_ip',
                'user_agent',
                'sha256_hash',
            ]);
        });
    }
};

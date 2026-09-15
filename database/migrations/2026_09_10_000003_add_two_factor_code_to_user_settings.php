<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time codes backing the email 2FA challenge (settings + login).
     */
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->string('two_factor_code', 255)->nullable()->after('two_factor_channel');
            $table->timestamp('two_factor_code_expires_at')->nullable()->after('two_factor_code');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['two_factor_code', 'two_factor_code_expires_at']);
        });
    }
};

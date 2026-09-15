<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('two_factor_code_attempts')->default(0)->after('two_factor_code_expires_at');
            $table->timestamp('two_factor_last_sent_at')->nullable()->after('two_factor_code_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['two_factor_code_attempts', 'two_factor_last_sent_at']);
        });
    }
};
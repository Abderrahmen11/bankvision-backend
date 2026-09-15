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
        // Per-user settings: 2FA, notification & UI preferences
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_channel')->nullable(); // email, sms, authenticator
            $table->json('notification_settings')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
        });

        // Institution-wide key/value configuration (bank profile, currency, interest rates)
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->index(); // bank, currency, interest
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Authentication event trail used by Security > Login History
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('event')->default('login'); // login, logout, failed_login
            $table->boolean('successful')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('device')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();
        });

        // Profile picture path on the public disk
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });

        Schema::dropIfExists('login_activities');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('user_settings');
    }
};

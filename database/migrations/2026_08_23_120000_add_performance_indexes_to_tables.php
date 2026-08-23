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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('transaction_date');
            $table->index('status');
            $table->index(['account_id', 'transaction_date']);
            $table->index(['account_id', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('customer_type');
            $table->index('kyc_status');
            $table->index('risk_level');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->index('status');
            $table->index('account_type');
            $table->index(['customer_id', 'status']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index('status');
            $table->index('loan_type');
            $table->index(['customer_id', 'status']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->index('status');
            $table->index('severity');
            $table->index('alert_type');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index('status');
            $table->index('city');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['account_id', 'transaction_date']);
            $table->dropIndex(['account_id', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_type']);
            $table->dropIndex(['kyc_status']);
            $table->dropIndex(['risk_level']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['account_type']);
            $table->dropIndex(['customer_id', 'status']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['loan_type']);
            $table->dropIndex(['customer_id', 'status']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['severity']);
            $table->dropIndex(['alert_type']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['city']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
        });
    }
};

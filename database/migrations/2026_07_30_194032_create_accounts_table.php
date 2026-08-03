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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('account_type'); // savings, checking, business
            $table->string('currency', 3)->default('USD');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status')->default('active'); // active, frozen, closed
            $table->date('opened_date');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

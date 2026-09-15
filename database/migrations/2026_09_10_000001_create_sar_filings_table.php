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
        Schema::create('sar_filings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();       // SAR-YYYY-XXXXXXXX
            $table->string('customer_name');
            $table->string('customer_number')->nullable();
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('under_review'); // draft, under_review, filed, escalated
            $table->text('narrative');
            $table->string('action_taken')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // filed by
            $table->foreignId('alert_id')->nullable()->constrained('alerts')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sar_filings');
    }
};

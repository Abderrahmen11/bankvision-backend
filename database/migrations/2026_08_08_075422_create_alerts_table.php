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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_number')->unique();
            $table->string('alert_type'); // suspicious_transaction, kyc_expiring, login_attempt, loan_delinquent
            $table->string('severity');   // high, medium, low
            $table->text('description');
            $table->timestamp('resolved_at')->nullable();
            $table->string('status')->default('open'); // open, in-progress, resolved
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Polymorphic relation columns
            $table->nullableMorphs('alertable'); // creates alertable_type (string) + alertable_id (unsignedBigInt) + index

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};

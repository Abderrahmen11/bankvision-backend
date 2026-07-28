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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code')->unique();
            $table->string('branch_name');
            $table->text('address')->nullable();
            $table->string('city');
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->integer('total_employees')->default(0);
            $table->string('status')->default('active'); // active, inactive, under_renovation
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};


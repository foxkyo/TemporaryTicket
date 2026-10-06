<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_initial_counts', function (Blueprint $table) {
            $table->id();
            $table->string('車別')->unique();
            $table->unsignedInteger('上學人數')->default(0);
            $table->unsignedInteger('放學人數')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_initial_counts');
    }
};

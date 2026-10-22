<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();

            $table->string('車別')->index();
            $table->string('站別')->index();
            $table->string('乘車處')->nullable();

            $table->unsignedInteger('單趟金額')->default(0);
            $table->unsignedInteger('單月金額')->default(0);

            $table->unsignedInteger('上學人數')->default(0);
            $table->unsignedInteger('放學人數')->default(0);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
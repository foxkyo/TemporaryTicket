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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('車別')->index();

            $table->unsignedInteger('搭車人數上限')->default(0);

            $table->boolean('上學管制')->default(false);
            $table->boolean('放學管制')->default(false);

            $table->unsignedInteger('上學搭車人數')->default(0);
            $table->unsignedInteger('放學搭車人數')->default(0);

            $table->unsignedInteger('已繳款人數')->default(0);
            $table->unsignedInteger('未繳款人數')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};

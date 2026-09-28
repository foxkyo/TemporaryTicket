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
        Schema::create('temporary_ticket_controls', function (Blueprint $table) {
            $table->id();

            $table->string('車別')->index();
            $table->boolean('上學管制')->default(false);
            $table->boolean('放學管制')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_ticket_controls');
    }
};
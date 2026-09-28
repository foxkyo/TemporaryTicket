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
        Schema::create('temporary_tickets', function (Blueprint $table) {
            $table->id();

            $table->string('學號');
            $table->string('班級');
            $table->string('座號');
            $table->string('姓名');

            $table->date('日期');
            $table->string('星期', 10);
            $table->json('時段');

            $table->foreignId('station_id')
                ->constrained('stations')
                ->restrictOnDelete();

            $table->string('車別');
            $table->string('站別');

            $table->string('barcode')->unique();

            $table->integer('金額');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_tickets');
    }
};

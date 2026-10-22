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
        // 移除人數相關欄位
        if (Schema::hasColumn('stations', '上學人數')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('上學人數');
            });
        }

        if (Schema::hasColumn('stations', '放學人數')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('放學人數');
            });
        }

        if (Schema::hasColumn('stations', '已繳款人數')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('已繳款人數');
            });
        }

        if (Schema::hasColumn('stations', '未繳款人數')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('未繳款人數');
            });
        }

        // 移除人數上限欄位（已移至 cars 表）
        if (Schema::hasColumn('stations', '人數上限')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('人數上限');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->unsignedInteger('上學人數')->default(0)->after('單月金額');
            $table->unsignedInteger('放學人數')->default(0)->after('上學人數');
            $table->unsignedInteger('已繳款人數')->default(0)->after('放學人數');
            $table->unsignedInteger('未繳款人數')->default(0)->after('已繳款人數');
            $table->unsignedInteger('人數上限')->default(0)->after('未繳款人數');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockAndCostToTblProductData extends Migration
{
    public function up()
    {
        Schema::table('tblProductData', function (Blueprint $table) {
            // Добавляем столбцы для уровня запасов и стоимости
            $table->integer('intStock')->nullable();
            $table->decimal('decimalCost', 10, 2)->nullable();
        });
    }

    public function down()
    {
        Schema::table('tblProductData', function (Blueprint $table) {
            // Удаляем столбцы при откате миграции
            $table->dropColumn(['intStock', 'decimalCost']);
        });
    }
}

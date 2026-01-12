<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromotionProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_products', function (Blueprint $table) {
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedInteger('product_id');
            
            // Clave primaria compuesta
            $table->primary(['promotion_id', 'product_id']);
            
            // Claves foráneas
            $table->foreign('promotion_id')
                  ->references('id')
                  ->on('promotions')
                  ->onDelete('cascade');
                  
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');
            
            // Índice adicional para consultas desde producto
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promotion_products');
    }
}

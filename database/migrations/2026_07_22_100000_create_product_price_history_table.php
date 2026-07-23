<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPriceHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->decimal('price_purchase', 12, 2)->nullable();
            $table->decimal('percentage_may', 5, 2)->nullable();
            $table->decimal('percentage_min', 5, 2)->nullable();
            $table->decimal('price_unit', 12, 2)->nullable();
            $table->decimal('price_min', 12, 2)->nullable();
            $table->string('source_type', 20);
            $table->unsignedInteger('source_id')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['product_id', 'occurred_at'], 'product_price_history_product_occurred_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_price_history');
    }
}

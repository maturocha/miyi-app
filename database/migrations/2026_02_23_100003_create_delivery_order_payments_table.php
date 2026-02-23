<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryOrderPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_order_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('delivery_order_id');
            $table->string('payment_method', 50);
            $table->decimal('amount', 12, 2);
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->foreign('delivery_order_id')
                ->references('id')
                ->on('delivery_orders')
                ->onDelete('cascade');

            $table->index('delivery_order_id', 'delivery_order_payments_delivery_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_order_payments');
    }
}


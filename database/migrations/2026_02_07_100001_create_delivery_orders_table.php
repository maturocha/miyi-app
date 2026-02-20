<?php

use App\DeliveryOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedInteger('order_id');
            $table->integer('sequence')->default(0)->nullable();
            $table->string('delivery_status', 50)->default(DeliveryOrderStatus::UNTOUCHED)->nullable();
            $table->decimal('collected_amount', 12, 2)->default(0)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('observations')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('delivery_id')->references('id')->on('deliveries')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // Unique constraint
            $table->unique(['delivery_id', 'order_id'], 'delivery_orders_unique');

            // Índices
            $table->index('order_id', 'delivery_orders_order_idx');
            $table->index(['delivery_id', 'sequence'], 'delivery_orders_delivery_sequence_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_orders');
    }
}

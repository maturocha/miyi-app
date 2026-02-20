<?php

use App\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('delivery_date');
            $table->string('status', 50)->default(DeliveryStatus::NOT_STARTED);
            $table->unsignedInteger('owner_user_id');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->decimal('expenses_amount', 12, 2)->default(0);
            $table->text('expenses_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('restrict');

            // Índices
            $table->index('status', 'deliveries_status_idx');
            $table->index(['owner_user_id', 'delivery_date'], 'deliveries_owner_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
}

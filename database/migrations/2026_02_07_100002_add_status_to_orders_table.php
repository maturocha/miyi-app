<?php

use App\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddStatusToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Nuevo default: pedido recién creado queda \"en proceso\"
            $table->string('status', 50)->default(OrderStatus::IN_PROCESS)->after('payment_method');
            $table->index('status', 'orders_status_idx');
        });
        DB::table('orders')->update(['status' => OrderStatus::DELIVERED]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_idx');
            $table->dropColumn('status');
        });
    }
}

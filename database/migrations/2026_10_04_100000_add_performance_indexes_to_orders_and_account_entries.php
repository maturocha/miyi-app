<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listado de pedidos (filtro por usuario + orden date desc, id desc) y
 * reportes de finanzas sobre account_entries (type, direction, occurred_at).
 */
class AddPerformanceIndexesToOrdersAndAccountEntries extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['id_user', 'date', 'id'], 'orders_user_date_id_idx');
            $table->index(['date', 'id'], 'orders_date_id_idx');
        });

        Schema::table('account_entries', function (Blueprint $table) {
            $table->index(['type', 'direction', 'occurred_at'], 'account_entries_type_dir_occurred_idx');
            $table->index('occurred_at', 'account_entries_occurred_idx');
        });
    }

    public function down()
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->dropIndex('account_entries_occurred_idx');
            $table->dropIndex('account_entries_type_dir_occurred_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_date_id_idx');
            $table->dropIndex('orders_user_date_id_idx');
        });
    }
}

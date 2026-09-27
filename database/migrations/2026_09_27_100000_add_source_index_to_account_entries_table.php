<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * (source_type, source_id) filtra los asientos de un pedido / reparto /
 * delivery_order: cierre y finalización de reparto (exists() por pedido),
 * detalle de reparto y validación en lote. Sin índice, cada consulta recorre
 * la tabla completa.
 */
class AddSourceIndexToAccountEntriesTable extends Migration
{
    public function up()
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->index(['source_type', 'source_id'], 'account_entries_source_idx');
        });
    }

    public function down()
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->dropIndex('account_entries_source_idx');
        });
    }
}

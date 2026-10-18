<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia de POST /account-entries: el cliente manda una clave por cobro y,
 * si reintenta tras un timeout, el servidor devuelve el asiento ya creado en vez
 * de duplicarlo. Único por usuario creador; los NULL (sin clave) no colisionan.
 */
class AddIdempotencyKeyToAccountEntriesTable extends Migration
{
    public function up()
    {
        Schema::table('account_entries', function (Blueprint $table) {
            // ascii_bin: comparación exacta ("abc" ≠ "ABC"); alcanza para [A-Za-z0-9_-].
            $table->string('idempotency_key', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->unique(['created_by_user_id', 'idempotency_key'], 'account_entries_creator_idempotency_unique');
        });
    }

    public function down()
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->dropUnique('account_entries_creator_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
}

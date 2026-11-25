<?php

namespace Tests\Concerns;

use App\Models\Enums\AccountEntryDirection;
use App\Models\Enums\AccountEntryValidationStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El esquema base (users, customers, orders...) no está en database/migrations
 * (viene de un dump previo a las migraciones). Para los tests se arma un esquema
 * mínimo con las columnas reales que usan estos endpoints, y account_entries se
 * crea con su migración real.
 */
trait BuildsLedgerSchema
{
    protected function buildLedgerSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('cel')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedInteger('role_id')->nullable();
            $table->timestamp('last_signin')->nullable();
            $table->text('auth_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cuit')->nullable();
            $table->string('name');
            $table->string('fullname')->nullable();
            $table->string('email')->nullable();
            $table->string('address');
            $table->string('time_visit')->nullable();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->string('cellphone');
            $table->string('telephone')->nullable();
            $table->date('birthday')->nullable();
            $table->unsignedInteger('id_neighborhood')->nullable();
            $table->char('type', 191)->default('mayorista');
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_customer');
            $table->unsignedInteger('id_user')->nullable();
            $table->date('date');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_order');
            $table->unsignedInteger('id_product');
            $table->decimal('price_final', 12, 2)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
        });

        $this->runMigration('2026_02_28_100000_create_account_entries_table.php', 'CreateAccountEntriesTable');
        $this->runMigration('2026_02_28_100001_create_account_entry_payment_methods_table.php', 'CreateAccountEntryPaymentMethodsTable');
        $this->runMigration('2026_09_27_100000_add_source_index_to_account_entries_table.php', 'AddSourceIndexToAccountEntriesTable');

        // La migración real usa collation ascii_bin (MySQL); en SQLite basta la columna.
        Schema::table('account_entries', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable();
        });
    }

    private function runMigration(string $file, string $class): void
    {
        require_once database_path('migrations/'.$file);
        (new $class())->up();
    }

    protected function createUser(int $roleId = 1): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Admin '.$roleId,
            'email' => 'admin'.$roleId.'.'.uniqid().'@example.test',
            'password' => 'x',
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createCustomer(string $name = 'Cliente'): int
    {
        return DB::table('customers')->insertGetId([
            'name' => $name,
            'address' => 'Calle 1',
            'cellphone' => '000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Inserta un movimiento sin pasar por el observer (el test controla id y fecha).
     */
    protected function createEntry(int $customerId, string $direction, string $amount, string $occurredAt, string $status = AccountEntryValidationStatus::VALIDATED, ?int $id = null): int
    {
        $row = [
            'customer_id' => $customerId,
            'type' => $direction === AccountEntryDirection::DEBIT ? 'charge' : 'payment',
            'direction' => $direction,
            'amount' => $amount,
            'occurred_at' => $occurredAt,
            'source_type' => 'manual',
            'validation_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($id !== null) {
            $row['id'] = $id;
        }

        return (int) DB::table('account_entries')->insertGetId($row);
    }
}

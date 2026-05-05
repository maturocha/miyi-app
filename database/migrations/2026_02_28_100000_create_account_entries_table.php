<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('customer_id');
            $table->string('type', 50);
            $table->string('direction', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_at_entry', 12, 2)->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('validation_status', 32)->default('not_validated');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['customer_id', 'occurred_at'], 'account_entries_customer_occurred_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_entries');
    }
}

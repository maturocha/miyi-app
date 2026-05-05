<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountEntryPaymentMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_entry_payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_entry_id');
            $table->string('payment_method', 50);
            $table->decimal('amount', 12, 2);
            $table->string('payment_reference', 191)->nullable();
            $table->timestamps();

            $table->foreign('account_entry_id')
                ->references('id')
                ->on('account_entries')
                ->onDelete('cascade');

            $table->index('account_entry_id', 'account_entry_payment_methods_entry_idx');
            $table->index('payment_method', 'account_entry_payment_methods_method_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_entry_payment_methods');
    }
}

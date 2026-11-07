<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBirthdayToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('customers', 'birthday')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('telephone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasColumn('customers', 'birthday')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }
}

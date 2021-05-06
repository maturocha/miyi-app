<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTableUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            //
            if(! Schema::hasColumn('verified_at', 'last_signin', 'auth_token', 'deleted_at')) {
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('last_signin')->nullable();
                $table->text('auth_token')->nullable();
                $table->softDeletes();
            }
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if(Schema::hasColumn('verified_at', 'last_signin', 'auth_token', 'deleted_at')) {
                $table->dropColumn('verified_at');
                $table->dropColumn('last_signin');
                $table->dropColumn('auth_token');
                $table->dropColumn('deleted_at');
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeDiscountToDecimalInOrdersAndOrderDetails extends Migration
{
    /**
     * Run the migrations.
     * Change discount column from int to decimal(5,2) in orders and order_details.
     */
    public function up(): void
    {
        DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('discount', 5, 2)->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount', 5, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedInteger('discount')->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('discount')->default(0)->change();
        });
    }
};

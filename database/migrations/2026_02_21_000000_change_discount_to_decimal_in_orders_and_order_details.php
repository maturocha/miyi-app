<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change discount column from int to decimal(5,2) in orders and order_details.
     */
    public function up(): void
    {
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
        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedInteger('discount')->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('discount')->default(0)->change();
        });
    }
};

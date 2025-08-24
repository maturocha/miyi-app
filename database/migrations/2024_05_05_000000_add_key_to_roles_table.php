<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AddKeyToRolesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('key')->unique()->nullable()->after('name');
        });

        // Poblar la columna 'key' con el slug del nombre
        $roles = DB::table('roles')->get();
        foreach ($roles as $role) {
            DB::table('roles')->where('id', $role->id)->update([
                'key' => Str::slug($role->name)
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('key');
        });
    }
}; 
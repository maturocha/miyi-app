<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CorrectUsersTableStructure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar columnas que ya no se necesitan
            if (Schema::hasColumn('users', 'logo_number')) {
                $table->dropColumn('logo_number');
            }
            
            if (Schema::hasColumn('users', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
            
            // Hacer cel nullable
            $table->string('cel')->nullable()->change();
            
            // Eliminar la foreign key existente si existe
            $foreignKeys = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys('users');
                
            foreach ($foreignKeys as $foreignKey) {
                if (in_array('role_id', $foreignKey->getLocalColumns())) {
                    $table->dropForeign($foreignKey->getName());
                    break;
                }
            }
            
            // Agregar la nueva foreign key con SET NULL en delete
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('SET NULL');
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
            // Restaurar columnas eliminadas
            $table->tinyInteger('logo_number')->default(1);
            $table->timestamp('verified_at')->nullable();
            
            // Hacer cel NOT NULL nuevamente
            $table->string('cel')->nullable(false)->change();
            
            // Eliminar la foreign key con SET NULL
            $foreignKeys = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableForeignKeys('users');
                
            foreach ($foreignKeys as $foreignKey) {
                if (in_array('role_id', $foreignKey->getLocalColumns())) {
                    $table->dropForeign($foreignKey->getName());
                    break;
                }
            }
            
            // Restaurar la foreign key original con CASCADE
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('CASCADE');
        });
    }
}

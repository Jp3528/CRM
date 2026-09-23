<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para las consultas de alcance (DataScope): búsquedas por
     * equipo y por autor/creador. Justificados por WHERE frecuentes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('team_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
        });
    }
};

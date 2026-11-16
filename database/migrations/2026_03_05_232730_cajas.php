<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_subagencia')->constrained('subagencias')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 100);
            $table->string('estado', 30)->default('activo');
            $table->timestamps();

            $table->unique(['id_subagencia', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};

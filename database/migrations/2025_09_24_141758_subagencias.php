<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      
        Schema::create('subagencias', function (Blueprint $table) {
            $table->id();
            //Datos de la sub agencia
            $table->string('nombre');
            $table->string('ubicacion');
            $table->date('fecha_apertura');
            $table->date('fecha_cierre');
            $table->string('direccion');
            $table->string('estado_agencia');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subagencias');
    }
};

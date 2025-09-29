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
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();
            //Datos del empleado
            $table->string('nombre');
            $table->string('cedula');
            $table->date('fecha_nacimiento');
            $table->string('inss');
            $table->string('nombre_banco');
            $table->string('numero_cuenta_banco');
            $table->string('estadotrabajador');

            //Datos de trabajo
            $table->date('fecha_contratacion');
            $table->unsignedBigInteger('id_subagencia');
            $table->foreign('id_subagencia')->references('id')->on('subagencias');
            $table->string('cargo');
            $table->float('salario_basico_inicial');
            $table->float('salario_basico_actual');
            $table->date('fecha_finalizacion');
            $table->string('concepto_finalizacion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};

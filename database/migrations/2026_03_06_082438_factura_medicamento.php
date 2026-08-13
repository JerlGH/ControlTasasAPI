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
        Schema::create('factura_medicamento', function (Blueprint $table) {
            $table->id();
            //Relación con el arqueo al que pertenece la factura
            $table->unsignedBigInteger('id_arqueo');
            $table->foreign('id_arqueo')->references('id')->on('arqueo');
            //Datos de la factura
            $table->string('estado');
            $table->string('awb');
            $table->string('nombre_expedidor');
            $table->string('direccion_expedidor');
            $table->string('ciudad_expedidor');
            $table->string('phn_expedidor');
            $table->string('nombre_consignatario');
            $table->string('direccion_consignatario');
            $table->string('ciudad_consignatario');
            $table->string('phn_consignatario');
            $table->integer('unidades_total');
            $table->float('peso_total');
            $table->float('monto_total');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factura_medicamento');
    }
};

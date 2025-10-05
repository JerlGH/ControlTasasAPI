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
        Schema::create('mesas_cambio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
            $table->foreign('id_usuario')->references('id')->on('users');
            $table->unsignedBigInteger('id_cliente');
            $table->foreign('id_cliente')->references('id')->on('clientes');
            //Datos de la tasa
            $table->date('fecha');
            $table->string('tipo_mesa');
            $table->string('precio');
            $table->string('monto_entrada');
            $table->string('monto_salida');
            $table->string('concepto');
            $table->boolean('archivo_adjunto');
            $table->string('estado_mesa');
            $table->timestamps();
        });
    }

    /*
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesas_cambio');
    }
};

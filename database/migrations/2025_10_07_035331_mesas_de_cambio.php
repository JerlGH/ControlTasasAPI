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
            //Datos de la mesa de cambio
            $table->dateTime('fecha');
            $table->string('tipo_transaccion');
            $table->float('tasa');
            $table->float('monto_entrada');
            $table->float('monto_salida');
            $table->string('concepto');
            $table->boolean('archivo_adjunto');
            $table->string('estado_mesa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesas_cambio');
    }
};

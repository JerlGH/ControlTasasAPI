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
        Schema::create('arqueo', function (Blueprint $table) {
            $table->id();
            //Relación con el usuario que realiza el arqueo
            $table->unsignedBigInteger('id_usuario');
            $table->foreign('id_usuario')->references('id')->on('users');
            //Datos del arqueo
            $table->string('estado');
            $table->date('fecha');

            //Resumen de operaciones
            $table->float('caja_inicial_cordobas');
            $table->float('caja_final_cordobas');
            $table->float('caja_inicial_dolares');
            $table->float('caja_final_dolares');
            $table->float('biller_cordobas');
            $table->float('biller_dolares');
            $table->float('envios_cordobas');
            $table->float('envios_dolares');
            $table->float('pagos_cordobas');
            $table->float('pagos_dolares');

            //Cheques
            $table->float('cantidad_cheques');
            $table->float('monto_total_cheques');
            $table->float('ganancia_total_cheques');

            //Diferencias
            $table->float('diferencia_cordobas');
            $table->float('diferencia_dolares');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arqueo');
    }
};

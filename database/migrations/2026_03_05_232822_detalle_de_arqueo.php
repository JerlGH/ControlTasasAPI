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
        Schema::create('detalle_arqueo', function (Blueprint $table) {
            $table->id();
            //Relación con el arqueo al que pertenece el detalle
            $table->unsignedBigInteger('id_arqueo');
            $table->foreign('id_arqueo')->references('id')->on('arqueo');
            //Datos del detalle del arqueo
            $table->string('estado');
            $table->string('moneda');
            $table->float('total');
            $table->integer('mil');
            $table->integer('quinientos');
            $table->integer('doscientos');
            $table->integer('cien');
            $table->integer('cincuenta');
            $table->integer('veinte');
            $table->integer('diez');
            $table->integer('cinco');
            $table->integer('dos');
            $table->integer('uno');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_arqueo');
    }
};

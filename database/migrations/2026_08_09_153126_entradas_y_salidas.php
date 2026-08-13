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
        Schema::create('entradas_y_salidas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_arqueo');
            $table->foreign('id_arqueo')->references('id')->on('arqueo');
            
            $table->string('estado');
            $table->string('tipo');
            $table->string('moneda');
            $table->float('monto');
            $table->string('observacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entradas_y_salidas');
    }
};

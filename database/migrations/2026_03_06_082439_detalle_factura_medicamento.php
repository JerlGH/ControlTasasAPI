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
        Schema::create('detalle_factura_medicamento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_factura');
            $table->foreign('id_factura')->references('id')->on('factura_medicamento');
            $table->unsignedBigInteger('id_medicamento');
            $table->foreign('id_medicamento')->references('id')->on('medicamentos');
            $table->string('estado');
            $table->string('uso_tratamiento');
            $table->string('prescripcion');
            $table->string('dosis_diaria');
            $table->integer('cantidad');
            $table->float('valor');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_factura_medicamento');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteos_servicios_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_servicio')->constrained('servicios_caja')->restrictOnDelete();
            // Cantidad declarada, no importe. La ausencia de fila significa no informado.
            $table->unsignedInteger('cantidad');
            $table->string('estado', 30)->default('registrado');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['id_arqueo', 'id_servicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteos_servicios_diarios');
    }
};

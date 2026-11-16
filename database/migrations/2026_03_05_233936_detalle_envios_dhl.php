<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_envios_dhl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_envio_dhl')->constrained('envios_dhl')->restrictOnDelete();
            // Desglose opcional del contenido; la descripcion original se conserva en el envio.
            $table->text('descripcion');
            $table->unsignedInteger('cantidad')->nullable();
            $table->string('unidad', 50)->nullable();
            $table->decimal('peso', 12, 3)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_envios_dhl');
    }
};

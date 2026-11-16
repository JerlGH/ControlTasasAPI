<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_cambio_arqueo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->enum('tipo', ['Compra', 'Venta']);
            $table->unsignedTinyInteger('numero_precio')->comment('Precio 1 a 4 del Excel');
            $table->decimal('tasa', 12, 6);
            $table->timestamps();

            $table->unique(['id_arqueo', 'tipo', 'numero_precio'], 'tasa_arqueo_tipo_precio_unique');
            $table->unique(['id', 'id_arqueo', 'tipo'], 'tasa_arqueo_identidad_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_cambio_arqueo');
    }
};

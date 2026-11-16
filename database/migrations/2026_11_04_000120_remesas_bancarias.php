<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remesas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->string('banco', 100);
            $table->enum('tipo', ['Entrada', 'Salida']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->string('numero_remesa', 100);
            $table->decimal('monto', 18, 2);
            $table->enum('medio', ['efectivo', 'cheque', 'transferencia', 'mixto'])->nullable();
            $table->dateTime('fecha_operacion')->nullable();
            $table->string('estado', 30)->default('registrado');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['id_arqueo', 'banco', 'tipo', 'moneda'], 'remesas_arqueo_banco_tipo_moneda');
            $table->index(['banco', 'numero_remesa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remesas_bancarias');
    }
};

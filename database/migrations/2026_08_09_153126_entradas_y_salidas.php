<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_y_salidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->enum('categoria', ['bancario', 'entre_agencias', 'gasto', 'varios']);
            $table->string('estado', 30)->default('registrado');
            $table->enum('tipo', ['Entrada', 'Salida']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 18, 2);
            $table->enum('medio', ['efectivo', 'cheque', 'transferencia', 'mixto'])->nullable();
            // Para los registros mixtos del Excel, cuando se conoce su composicion.
            $table->decimal('monto_efectivo', 18, 2)->nullable();
            $table->decimal('monto_cheques', 18, 2)->nullable();
            $table->string('detalle')->nullable()->comment('Concepto de gasto o contraparte declarada');
            $table->string('banco', 100)->nullable();
            $table->string('numero_cuenta', 100)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->foreignId('id_subagencia_contraparte')->nullable()
                ->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_movimiento_relacionado')->nullable()->unique()
                ->constrained('entradas_y_salidas');
            $table->dateTime('fecha_operacion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['id_arqueo', 'categoria', 'tipo', 'moneda'], 'movimientos_arqueo_categoria_moneda');
            $table->index('referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas_y_salidas');
    }
};

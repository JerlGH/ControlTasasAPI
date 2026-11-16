<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones_cambio_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            // Vinculo opcional con la operacion existente, sin alterar mesas_cambio ni clientes.
            $table->foreignId('id_mesa_cambio')->nullable()->unique()
                ->constrained('mesas_cambio')->restrictOnDelete();
            $table->foreignId('id_cliente')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('id_tasa_arqueo')->nullable();
            $table->enum('tipo', ['Compra', 'Venta']);
            $table->decimal('monto_dolares', 18, 2);
            $table->decimal('tasa', 12, 6)->comment('Tasa aplicada a esta operacion, aunque luego cambie el precio del dia');
            $table->decimal('monto_cordobas', 24, 8)
                ->comment('Importe aplicado o calculado en origen; conserva fracciones de centavo del Excel');
            $table->enum('medio_entrada', ['efectivo', 'cheque', 'transferencia', 'mixto'])->nullable();
            $table->enum('medio_salida', ['efectivo', 'cheque', 'transferencia', 'mixto'])->nullable();
            $table->dateTime('fecha_operacion')->nullable();
            $table->string('estado', 30)->default('registrado');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['id_arqueo', 'tipo']);
            // Una tasa vinculada debe pertenecer a la misma jornada y al mismo tipo de operacion.
            $table->foreign(['id_tasa_arqueo', 'id_arqueo', 'tipo'], 'operacion_tasa_arqueo_tipo_foreign')
                ->references(['id', 'id_arqueo', 'tipo'])->on('tasas_cambio_arqueo')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operaciones_cambio_caja');
    }
};

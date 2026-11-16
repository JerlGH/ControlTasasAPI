<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_personales', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique('personal_clave_u');
            $table->foreignId('id_cuenta')->constrained('cuentas_financieras')->restrictOnDelete();
            $table->foreignId('id_operacion')->constrained('operaciones_financieras')->restrictOnDelete();
            $table->date('fecha');
            $table->enum('tipo', ['deposito', 'retiro', 'prestamo_entregado']);
            $table->decimal('monto', 24, 8);
            $table->enum('moneda_origen', ['NIO', 'USD']);
            $table->decimal('tasa_cambio', 12, 6)->nullable();
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto_cuenta', 24, 8);
            $table->decimal('saldo_anterior', 32, 8);
            $table->decimal('saldo_posterior', 32, 8);
            $table->decimal('fondos_utilizados', 24, 8)->default(0);
            $table->decimal('fondos_incrementados', 24, 8)->default(0);
            $table->decimal('deuda_generada', 24, 8)->default(0);
            $table->decimal('deuda_cancelada', 24, 8)->default(0);
            $table->foreignId('id_movimiento_generado')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->text('concepto');
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_cuenta', 'fecha'], 'personal_cuenta_fecha_i');
            $table->index(['id_operacion', 'estado'], 'personal_operacion_estado_i');
            $table->unique('id_movimiento_generado', 'personal_movimiento_u');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_personales');
    }
};

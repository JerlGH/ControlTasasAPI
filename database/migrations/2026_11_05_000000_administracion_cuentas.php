<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos_financieros', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        Schema::create('contrapartes_financieras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('nombre', 255);
            $table->enum('tipo', ['persona', 'empresa', 'banco', 'proveedor', 'propietario', 'colaborador', 'otro']);
            $table->foreignId('id_cliente')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('id_trabajador')->nullable()->constrained('trabajadores')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['codigo'], 'fin_2_u0');
        });

        Schema::create('documentos_financieros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('referencia', 255)->nullable();
            $table->text('ubicacion');
            $table->string('sha256', 64);
            $table->string('tipo', 100);
            $table->date('fecha_documento')->nullable();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['sha256'], 'fin_3_u0');
        });

        Schema::create('cuentas_financieras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('nombre', 255);
            $table->enum('tipo', ['banco', 'pos', 'por_cobrar', 'por_pagar', 'fondo', 'recargas', 'liquidacion']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->enum('rubro_capital', ['banco', 'pendiente', 'deuda', 'excluido'])->default('excluido');
            $table->date('fecha_apertura');
            $table->decimal('saldo_inicial', 24, 8);
            $table->text('observacion')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['codigo'], 'fin_4_u0');
        });

        Schema::create('operaciones_financieras', function (Blueprint $table) {
            $table->id();
            $table->string('clave_origen_activa', 100)->nullable()->unique('fin_op_origen_u');
            $table->date('fecha');
            $table->enum('tipo', ['ingreso', 'egreso', 'transferencia']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->text('concepto');
            $table->string('clave', 100);
            $table->foreignId('id_cuenta_origen')->nullable()->constrained('cuentas_financieras')->restrictOnDelete();
            $table->foreignId('id_cuenta_destino')->nullable()->constrained('cuentas_financieras')->restrictOnDelete();
            $table->enum('origen_tipo', ['entrada_salida', 'pase', 'remesa', 'cambio_entrada', 'cambio_salida'])->nullable();
            $table->unsignedBigInteger('id_origen')->nullable();
            $table->foreignId('id_entrada_salida')->nullable()->constrained('entradas_y_salidas')->restrictOnDelete();
            $table->foreignId('id_pase')->nullable()->constrained('pases')->restrictOnDelete();
            $table->foreignId('id_remesa')->nullable()->constrained('remesas_bancarias')->restrictOnDelete();
            $table->foreignId('id_operacion_cambio')->nullable()->constrained('operaciones_cambio_caja')->restrictOnDelete();
            $table->enum('lado', ['entrada', 'salida'])->nullable();
            $table->json('origen_snapshot')->nullable();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['clave'], 'fin_5_u0');
        });

        Schema::create('movimientos_cuenta_financiera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cuenta')->constrained('cuentas_financieras')->restrictOnDelete();
            $table->date('fecha');
            $table->enum('tipo', ['cargo', 'abono']);
            $table->decimal('monto', 24, 8);
            $table->text('concepto');
            $table->foreignId('id_operacion')->nullable()->constrained('operaciones_financieras')->restrictOnDelete();
            $table->string('origen_automatico', 100)->nullable();
            $table->string('clave', 100);
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['clave'], 'fin_6_u0');
            $table->index(['id_cuenta', 'fecha'], 'fin_6_i0');
        });

        Schema::create('aplicaciones_cuenta_financiera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cargo')->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_abono')->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 24, 8);
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_cargo', 'id_abono'], 'fin_7_i0');
        });

        Schema::create('reportes_matriz', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->dateTime('corte_at');
            $table->string('proveedor', 100);
            $table->string('referencia', 100);
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->enum('estado', ['borrador', 'confirmado', 'anulado'])->default('borrador');
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['proveedor', 'referencia'], 'fin_8_u0');
        });

        Schema::create('detalles_reporte_matriz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_reporte')->constrained('reportes_matriz')->restrictOnDelete();
            $table->enum('servicio', ['wu', 'biller']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_reporte', 'servicio', 'moneda'], 'fin_9_i0');
        });

        Schema::create('conciliaciones_matriz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo_madre')->constrained('arqueo_madre')->restrictOnDelete();
            $table->foreignId('id_reporte')->constrained('reportes_matriz')->restrictOnDelete();
            $table->json('resultado');
            $table->enum('estado', ['conciliado', 'con_diferencias', 'revisado']);
            $table->text('motivo_revision')->nullable();
            $table->dateTime('revisado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id_arqueo_madre', 'id_reporte'], 'fin_10_u0');
        });

        Schema::create('liquidaciones_matriz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo_madre')->constrained('arqueo_madre')->restrictOnDelete();
            $table->date('fecha');
            $table->foreignId('id_anterior')->nullable()->constrained('liquidaciones_matriz')->restrictOnDelete();
            $table->decimal('saldo_anterior_usd', 24, 8)->nullable();
            $table->text('motivo_saldo_inicial')->nullable();
            $table->decimal('anticipo_usd', 24, 8)->default('0');
            $table->decimal('comision_anticipo_usd', 24, 8)->default('0');
            $table->decimal('saldo_matriz_nio', 24, 8)->nullable();
            $table->decimal('saldo_interno_nio', 24, 8)->nullable();
            $table->dateTime('corte_comparacion_at')->nullable();
            $table->json('resultado')->nullable();
            $table->enum('estado', ['borrador', 'cerrado', 'anulado'])->default('borrador');
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_arqueo_madre'], 'fin_11_i0');
            $table->index(['id_anterior'], 'fin_11_i1');
        });

        Schema::create('depositos_liquidacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cuenta_transito')->nullable()->constrained('cuentas_financieras')->restrictOnDelete();
            $table->foreignId('id_movimiento_transito')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_movimiento_acreditacion')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->string('clave_operacion_activa', 100)->nullable()->unique('fin_dep_operacion_u');
            $table->foreignId('id_liquidacion')->constrained('liquidaciones_matriz')->restrictOnDelete();
            $table->foreignId('id_operacion')->constrained('operaciones_financieras')->restrictOnDelete();
            $table->date('fecha');
            $table->date('fecha_acreditacion')->nullable();
            $table->enum('estado', ['pendiente', 'acreditado', 'anulado'])->default('pendiente');
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depositos_liquidacion');
        Schema::dropIfExists('liquidaciones_matriz');
        Schema::dropIfExists('conciliaciones_matriz');
        Schema::dropIfExists('detalles_reporte_matriz');
        Schema::dropIfExists('reportes_matriz');
        Schema::dropIfExists('aplicaciones_cuenta_financiera');
        Schema::dropIfExists('movimientos_cuenta_financiera');
        Schema::dropIfExists('operaciones_financieras');
        Schema::dropIfExists('cuentas_financieras');
        Schema::dropIfExists('documentos_financieros');
        Schema::dropIfExists('contrapartes_financieras');
        Schema::dropIfExists('bloqueos_financieros');
    }
};

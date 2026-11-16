<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('nombre', 255);
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['codigo'], 'fin_13_u0');
        });

        Schema::create('gastos_administrativos', function (Blueprint $table) {
            $table->id();
            $table->string('origen_automatico', 100)->nullable()->unique('fin_gasto_auto_u');
            $table->date('fecha');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->foreignId('id_centro_costo')->nullable()->constrained('centros_costo')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->enum('ambito', ['administrativo', 'operativo', 'personal']);
            $table->string('categoria', 100);
            $table->text('concepto');
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->foreignId('id_cuenta')->nullable()->constrained('cuentas_financieras')->restrictOnDelete();
            $table->foreignId('id_movimiento_origen')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('obligaciones_laborales', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->foreignId('id_trabajador')->constrained('trabajadores')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_cuenta')->constrained('cuentas_financieras')->restrictOnDelete();
            $table->enum('concepto', ['salario', 'aguinaldo', 'vacaciones', 'indemnizacion', 'inss_laboral', 'inss_patronal', 'inatec', 'anticipo', 'descuento', 'otro']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->foreignId('id_operacion_anticipo')->nullable()->constrained('operaciones_financieras')->restrictOnDelete();
            $table->foreignId('id_movimiento_origen')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('comisiones_administrativas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->enum('tipo', ['proveedor', 'operador', 'bancaria']);
            $table->enum('naturaleza', ['cobrar', 'pagar']);
            $table->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->foreignId('id_servicio')->nullable()->constrained('servicios_caja')->restrictOnDelete();
            $table->foreignId('id_trabajador')->nullable()->constrained('trabajadores')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_cuenta')->constrained('cuentas_financieras')->restrictOnDelete();
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->foreignId('id_movimiento_origen')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('proyectos_obra', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('nombre', 255);
            $table->date('fecha');
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['codigo'], 'fin_17_u0');
        });

        Schema::create('movimientos_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_proyecto')->constrained('proyectos_obra')->restrictOnDelete();
            $table->date('fecha');
            $table->enum('tipo', ['costo', 'anticipo', 'recuperacion', 'devolucion']);
            $table->enum('partida', ['material', 'mano_obra', 'servicio', 'otro']);
            $table->text('concepto');
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->decimal('cantidad', 18, 6)->nullable();
            $table->decimal('precio_unitario', 24, 8)->nullable();
            $table->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('aplicaciones_anticipo_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_anticipo')->constrained('movimientos_obra')->restrictOnDelete();
            $table->foreignId('id_costo')->constrained('movimientos_obra')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 24, 8);
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_anticipo', 'id_costo'], 'fin_19_i0');
        });

        Schema::create('activos_administrativos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60);
            $table->string('nombre', 255);
            $table->enum('categoria', ['terreno', 'edificio', 'equipo', 'vehiculo', 'otro']);
            $table->date('fecha');
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('cantidad', 18, 6)->default('1');
            $table->decimal('precio_unitario', 24, 8);
            $table->decimal('monto', 24, 8);
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['codigo'], 'fin_20_u0');
        });

        Schema::create('eventos_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_activo')->constrained('activos_administrativos')->restrictOnDelete();
            $table->date('fecha');
            $table->enum('tipo', ['traslado', 'baja']);
            $table->decimal('cantidad', 18, 6);
            $table->foreignId('id_subagencia_origen')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_subagencia_destino')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->text('concepto');
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('financiamientos', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 100);
            $table->foreignId('id_cuenta')->constrained('cuentas_financieras')->restrictOnDelete();
            $table->foreignId('id_contraparte')->constrained('contrapartes_financieras')->restrictOnDelete();
            $table->date('fecha');
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('principal', 24, 8);
            $table->decimal('tasa_interes', 12, 6)->nullable();
            $table->enum('base_tasa', ['mensual', 'anual', 'otra'])->nullable();
            $table->enum('periodicidad', ['mensual', 'anual', 'otra']);
            $table->unsignedBigInteger('numero_cuotas')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->foreignId('id_operacion_desembolso')->nullable()->constrained('operaciones_financieras')->restrictOnDelete();
            $table->foreignId('id_movimiento_origen')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['referencia'], 'fin_22_u0');
            $table->unique(['id_cuenta'], 'fin_22_u1');
        });

        Schema::create('cuotas_financiamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_financiamiento')->constrained('financiamientos')->restrictOnDelete();
            $table->unsignedBigInteger('numero');
            $table->date('fecha');
            $table->decimal('capital', 24, 8);
            $table->decimal('interes', 24, 8)->default('0');
            $table->decimal('seguro', 24, 8)->default('0');
            $table->decimal('comision', 24, 8)->default('0');
            $table->decimal('monto', 24, 8);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_financiamiento', 'numero'], 'fin_23_i0');
        });

        Schema::create('incidencias_financieras', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('referencia', 100);
            $table->enum('servicio', ['biller', 'dhl', 'remesa', 'cambio', 'otro']);
            $table->foreignId('id_arqueo')->nullable()->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_envio_dhl')->nullable()->constrained('envios_dhl')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_operador')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('biller', 100)->nullable();
            $table->string('referencia_cliente', 255)->nullable();
            $table->string('ticket', 100)->nullable();
            $table->string('caso', 100)->nullable();
            $table->text('motivo_incidencia');
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 24, 8);
            $table->foreignId('id_cuenta')->nullable()->constrained('cuentas_financieras')->restrictOnDelete();
            $table->foreignId('id_movimiento_origen')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->enum('gestion', ['abierto', 'solicitado', 'confirmado', 'cerrado'])->default('abierto');
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['referencia'], 'fin_24_u0');
        });

        Schema::create('gestiones_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_incidencia')->constrained('incidencias_financieras')->restrictOnDelete();
            $table->date('fecha');
            $table->enum('gestion', ['abierto', 'solicitado', 'confirmado', 'cerrado']);
            $table->text('detalle');
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('aplicaciones_financieras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_operacion')->constrained('operaciones_financieras')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 24, 8);
            $table->decimal('tasa_cambio', 12, 6)->nullable();
            $table->decimal('monto_destino', 24, 8);
            $table->foreignId('id_gasto')->nullable()->constrained('gastos_administrativos')->restrictOnDelete();
            $table->foreignId('id_obligacion')->nullable()->constrained('obligaciones_laborales')->restrictOnDelete();
            $table->foreignId('id_comision')->nullable()->constrained('comisiones_administrativas')->restrictOnDelete();
            $table->foreignId('id_obra')->nullable()->constrained('movimientos_obra')->restrictOnDelete();
            $table->foreignId('id_activo')->nullable()->constrained('activos_administrativos')->restrictOnDelete();
            $table->foreignId('id_cuota')->nullable()->constrained('cuotas_financiamiento')->restrictOnDelete();
            $table->foreignId('id_financiamiento')->nullable()->constrained('financiamientos')->restrictOnDelete();
            $table->foreignId('id_incidencia')->nullable()->constrained('incidencias_financieras')->restrictOnDelete();
            $table->foreignId('id_cargo')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->json('componentes')->nullable();
            $table->foreignId('id_movimiento_generado')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_aplicacion_cuenta_generada')->nullable()->constrained('aplicaciones_cuenta_financiera')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['id_operacion', 'fecha'], 'fin_26_i0');
        });

        Schema::create('cierres_financieros', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->date('periodo_desde');
            $table->foreignId('id_arqueo_madre')->constrained('arqueo_madre')->restrictOnDelete();
            $table->foreignId('id_liquidacion')->constrained('liquidaciones_matriz')->restrictOnDelete();
            $table->json('cuentas_capital_trabajo')->nullable();
            $table->json('datos');
            $table->string('huella', 64);
            $table->dateTime('cerrado_at');
            $table->text('motivo');
            $table->enum('estado', ['cerrado'])->default('cerrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fecha'], 'fin_27_u0');
        });

        Schema::create('historial_financiero', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 100);
            $table->unsignedBigInteger('id_registro');
            $table->string('accion', 60);
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->text('motivo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['entidad', 'id_registro'], 'fin_28_i0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_financiero');
        Schema::dropIfExists('cierres_financieros');
        Schema::dropIfExists('aplicaciones_financieras');
        Schema::dropIfExists('gestiones_incidencia');
        Schema::dropIfExists('incidencias_financieras');
        Schema::dropIfExists('cuotas_financiamiento');
        Schema::dropIfExists('financiamientos');
        Schema::dropIfExists('eventos_activo');
        Schema::dropIfExists('activos_administrativos');
        Schema::dropIfExists('aplicaciones_anticipo_obra');
        Schema::dropIfExists('movimientos_obra');
        Schema::dropIfExists('proyectos_obra');
        Schema::dropIfExists('comisiones_administrativas');
        Schema::dropIfExists('obligaciones_laborales');
        Schema::dropIfExists('gastos_administrativos');
        Schema::dropIfExists('centros_costo');
    }
};

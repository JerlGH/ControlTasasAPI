<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_remesa_central', function (Blueprint $t) {
            $t->id();
            $t->date('fecha');
            $t->dateTime('corte_at');
            $t->string('referencia', 100)->unique('ld_reporte_ref_u');
            $t->string('fuente', 255);
            $t->json('agencias_esperadas');
            $t->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $t->enum('estado', ['borrador', 'confirmado', 'anulado'])->default('borrador');
            $t->dateTime('confirmado_at')->nullable();
            $t->text('observacion')->nullable();
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('detalles_remesa_central', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_reporte')->constrained('reportes_remesa_central')->restrictOnDelete();
            $t->foreignId('id_subagencia')->constrained('subagencias')->restrictOnDelete();
            $t->enum('banco', ['lafise', 'banpro']);
            $t->enum('moneda', ['NIO', 'USD']);
            $t->enum('tipo', ['Entrada', 'Salida']); // Perspectiva central/POS, inversa al cajero.
            $t->decimal('monto', 24, 8); // Cero declarado es distinto de linea ausente.
            $t->string('clave_activa', 150)->nullable()->unique('ld_remesa_linea_u');
            $t->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $t->text('observacion')->nullable();
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('libros_diarios', function (Blueprint $t) {
            $t->id();
            $t->date('fecha');
            $t->unsignedInteger('version');
            $t->foreignId('id_anterior')->nullable()->constrained('libros_diarios')->restrictOnDelete();
            $t->foreignId('id_reporte_remesas')->nullable()->constrained('reportes_remesa_central')->restrictOnDelete();
            $t->json('usuarios_esperados');
            $t->text('motivo_apertura')->nullable();
            $t->text('observacion')->nullable();
            $t->enum('estado', ['borrador', 'cerrado', 'anulado'])->default('borrador');
            $t->json('resultado')->nullable();
            $t->string('huella', 64)->nullable();
            $t->dateTime('cerrado_at')->nullable();
            $t->foreignId('id_usuario_cierre')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['fecha', 'version'], 'ld_fecha_version_u');
        });
        Schema::create('fuentes_libro_diario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_libro_diario')->constrained('libros_diarios')->restrictOnDelete();
            $t->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $t->foreignId('id_arqueo')->nullable()->constrained('arqueo')->restrictOnDelete();
            $t->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $t->string('estado_fuente', 30);
            $t->json('datos')->nullable();
            $t->string('huella', 64);
            $t->timestamps();
            $t->unique(['id_libro_diario', 'id_usuario'], 'ld_fuente_usuario_u');
        });
        Schema::create('cuentas_libro_diario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_libro_diario')->constrained('libros_diarios')->restrictOnDelete();
            $t->foreignId('id_cuenta')->constrained('cuentas_financieras')->restrictOnDelete();
            $t->enum('canal', ['bancentro', 'lafise', 'banpro']);
            $t->enum('moneda', ['NIO', 'USD']);
            $t->string('clave_activa', 100)->nullable()->unique('ld_cuenta_slot_u');
            $t->string('clave_cuenta_activa', 100)->nullable()->unique('ld_cuenta_id_u');
            $t->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('partidas_libro_diario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_libro_diario')->constrained('libros_diarios')->restrictOnDelete();
            $t->foreignId('id_operacion')->constrained('operaciones_financieras')->restrictOnDelete();
            $t->enum('bloque', ['banco_cajeros', 'cambio_tesoreria', 'adicional', 'gasto', 'cuenta_particular', 'remesa_pos', 'efectivo_airpak']);
            $t->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $t->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $t->string('receptor', 255)->nullable();
            $t->string('referencia', 100)->nullable();
            $t->text('observacion')->nullable();
            $t->string('clave_activa', 100)->nullable()->unique('ld_partida_operacion_u');
            $t->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('detalles_cuenta_diario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_libro_diario')->constrained('libros_diarios')->restrictOnDelete();
            $t->foreignId('id_entrada_salida')->constrained('entradas_y_salidas')->restrictOnDelete();
            $t->foreignId('id_cuenta_auxiliar')->nullable()->constrained('cuentas_financieras')->restrictOnDelete();
            $t->foreignId('id_contraparte')->nullable()->constrained('contrapartes_financieras')->restrictOnDelete();
            $t->foreignId('id_operacion')->nullable()->constrained('operaciones_financieras')->restrictOnDelete();
            $t->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $t->text('observacion')->nullable();
            $t->string('clave_activa', 100)->nullable()->unique('ld_detalle_origen_u');
            $t->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('revisiones_libro_diario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_libro_diario')->constrained('libros_diarios')->restrictOnDelete();
            $t->string('clave_control', 150);
            $t->string('huella', 64);
            $t->enum('resultado', ['revisado', 'observado', 'justificado']);
            $t->enum('estado', ['registrado'])->default('registrado');
            $t->text('detalle');
            $t->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $t->string('referencia', 100)->nullable();
            $t->dateTime('revisado_at');
            $t->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['id_libro_diario', 'clave_control'], 'ld_revision_control_i');
        });
        Schema::table('arqueo_madre', function (Blueprint $t) {
            $t->foreignId('id_libro_diario')->nullable()->constrained('libros_diarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('arqueo_madre', function (Blueprint $t) {
            $t->dropConstrainedForeignId('id_libro_diario');
        });
        foreach (['revisiones_libro_diario', 'detalles_cuenta_diario', 'partidas_libro_diario', 'cuentas_libro_diario',
            'fuentes_libro_diario', 'libros_diarios', 'detalles_remesa_central', 'reportes_remesa_central'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

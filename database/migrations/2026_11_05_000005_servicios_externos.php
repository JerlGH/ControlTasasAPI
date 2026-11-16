<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqueos_servicios_externos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('terminal', 60)->default('principal');
            $table->string('clave_activa', 100)->nullable()->unique('ase_clave_activa_uq');
            $table->json('agencias_esperadas');
            $table->foreignId('id_responsable')->constrained('users')->restrictOnDelete();
            $table->json('tarifario');
            $table->json('resumen')->nullable();
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->enum('estado', ['borrador', 'cerrado', 'anulado'])->default('borrador');
            $table->timestamp('cerrado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['fecha', 'estado'], 'ase_fecha_estado_ix');
        });

        Schema::create('transacciones_servicios_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo_externo')->constrained('arqueos_servicios_externos')->restrictOnDelete();
            // Clave de captura: el numero de remesa del Excel tambien contiene cuentas repetidas.
            $table->string('clave', 100);
            $table->foreignId('id_subagencia')->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_cajero')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('nombre_cajero', 255)->nullable();
            $table->enum('banco', ['lafise', 'banpro']);
            $table->string('servicio', 60);
            $table->text('motivo_sin_comision')->nullable();
            // Perspectiva central/POS, igual que el reporte de Libro Diario.
            $table->enum('tipo', ['Entrada', 'Salida']);
            $table->enum('moneda', ['NIO', 'USD']);
            $table->string('numero_referencia', 100);
            $table->decimal('monto', 24, 8);
            $table->decimal('monto_remesa_usd', 18, 2)->nullable();
            $table->foreignId('id_remesa_caja')->nullable()->constrained('remesas_bancarias')->restrictOnDelete();
            $table->string('clave_remesa_activa', 100)->nullable()->unique('tse_remesa_activa_uq');
            $table->foreignId('id_documento')->nullable()->constrained('documentos_financieros')->restrictOnDelete();
            $table->decimal('tarifa_nio', 24, 8)->nullable();
            $table->decimal('ganancia_nio', 24, 8)->nullable();
            $table->json('calculo')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id_arqueo_externo', 'clave'], 'tse_captura_uq');
            $table->index(['id_arqueo_externo', 'banco', 'servicio'], 'tse_servicio_ix');
        });

        Schema::table('reportes_remesa_central', function (Blueprint $table) {
            $table->foreignId('id_arqueo_externo')->nullable()->unique('rrc_externo_uq')
                ->constrained('arqueos_servicios_externos')->restrictOnDelete();
        });
        Schema::table('comisiones_administrativas', function (Blueprint $table) {
            $table->foreignId('id_arqueo_externo')->nullable()->constrained('arqueos_servicios_externos')->restrictOnDelete();
            $table->enum('banco_externo', ['lafise', 'banpro'])->nullable();
            $table->string('clave_externa_activa', 100)->nullable()->unique('ca_externo_activo_uq');
            $table->index(['id_arqueo_externo', 'banco_externo'], 'ca_externo_banco_ix');
        });
    }

    public function down(): void
    {
        Schema::table('comisiones_administrativas', function (Blueprint $table) {
            $table->dropUnique('ca_externo_activo_uq');
            $table->dropIndex('ca_externo_banco_ix');
            $table->dropForeign(['id_arqueo_externo']);
            $table->dropColumn(['id_arqueo_externo', 'banco_externo', 'clave_externa_activa']);
        });
        Schema::table('reportes_remesa_central', function (Blueprint $table) {
            $table->dropForeign(['id_arqueo_externo']);
            $table->dropUnique('rrc_externo_uq');
            $table->dropColumn('id_arqueo_externo');
        });
        Schema::dropIfExists('transacciones_servicios_externos');
        Schema::dropIfExists('arqueos_servicios_externos');
    }
};

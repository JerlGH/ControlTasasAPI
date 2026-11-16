<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqueo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            // Conserva la agencia de la jornada aunque el trabajador cambie de agencia.
            $table->foreignId('id_subagencia')->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_caja')->nullable()->constrained('cajas')->restrictOnDelete();
            $table->foreignId('id_arqueo_anterior')->nullable()->unique()
                ->constrained('arqueo');
            $table->date('fecha');
            $table->string('estado', 30)->default('abierto');
            $table->dateTime('abierto_at')->nullable();
            $table->dateTime('cerrado_at')->nullable();
            $table->dateTime('cheques_confirmados_at')->nullable();
            $table->foreignId('id_usuario_cierre')->nullable()
                ->constrained('users')->restrictOnDelete();

            // Apertura capturada o trasladada del cierre contado, como AirPak1.
            // Los saldos totales incluyen cheques; el efectivo se obtiene por diferencia.
            $table->decimal('caja_inicial_cordobas', 18, 2);
            $table->decimal('caja_inicial_dolares', 18, 2);
            $table->decimal('cheques_inicial_cordobas', 18, 2)->nullable();
            $table->decimal('cheques_inicial_dolares', 18, 2)->nullable();
            $table->decimal('tasa_referencia', 12, 6)->nullable()
                ->comment('T/C usado por esta jornada; no consultar el ultimo precio para recalcular historia');
            $table->text('motivo_apertura_manual')->nullable();

            // Totales diarios declarados en E2:E5 y H4:H5. Conservar el signo del Excel.
            // NULL significa no informado; cero significa un total informado sin operaciones.
            // Envios y pagos son remesas de plataforma; DHL tiene registros independientes.
            $table->decimal('biller_cordobas', 18, 2)->nullable();
            $table->decimal('biller_dolares', 18, 2)->nullable();
            $table->decimal('envios_cordobas', 18, 2)->nullable();
            $table->decimal('envios_dolares', 18, 2)->nullable();
            $table->decimal('pagos_cordobas', 18, 2)->nullable();
            $table->decimal('pagos_dolares', 18, 2)->nullable();

            // Resultados de cierre pendientes de calcular: NULL no equivale a un cierre en cero.
            $table->decimal('caja_final_cordobas', 18, 2)->nullable();
            $table->decimal('caja_final_dolares', 18, 2)->nullable();
            $table->decimal('saldo_esperado_cordobas', 24, 8)->nullable();
            $table->decimal('saldo_esperado_dolares', 24, 8)->nullable();
            // Las conversiones del Excel generan fracciones de centavo; no redondear al capturar.
            $table->decimal('diferencia_cordobas', 24, 8)->nullable();
            $table->decimal('diferencia_dolares', 24, 8)->nullable();
            $table->unsignedInteger('cantidad_cheques')->nullable();
            $table->decimal('monto_total_cheques', 18, 2)->nullable()
                ->comment('Resumen en NIO; nunca sumar principal de monedas distintas');
            $table->decimal('monto_total_cheques_dolares', 18, 2)->nullable();
            $table->decimal('ganancia_total_cheques', 18, 2)->nullable()
                ->comment('Ganancia en NIO de las operaciones del dia, sin repetir cheques arrastrados');
            $table->decimal('ganancia_total_cheques_dolares', 18, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['id_usuario', 'fecha']);
            $table->index(['id_subagencia', 'fecha']);
            $table->index(['id_caja', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueo');
    }
};

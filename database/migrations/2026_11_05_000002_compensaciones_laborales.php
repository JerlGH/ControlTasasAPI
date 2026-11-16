<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensaciones_laborales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_anticipo')->nullable()->constrained('obligaciones_laborales')->restrictOnDelete();
            $table->foreignId('id_retencion')->nullable()->constrained('obligaciones_laborales')->restrictOnDelete();
            $table->foreignId('id_obligacion')->constrained('obligaciones_laborales')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 24, 8);
            $table->foreignId('id_abono_anticipo')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->foreignId('id_abono_obligacion')->nullable()->constrained('movimientos_cuenta_financiera')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensaciones_laborales');
    }
};

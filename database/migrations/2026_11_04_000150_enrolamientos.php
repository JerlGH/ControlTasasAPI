<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_cliente')->nullable()->constrained('clientes')->restrictOnDelete();
            // No asociar el conteo de servicios de una fila del Excel con la persona de esa fila.
            $table->foreignId('id_servicio')->nullable()
                ->constrained('servicios_caja')->restrictOnDelete();
            $table->date('fecha_enrolamiento')->nullable();
            $table->string('fecha_original', 100)->nullable();
            $table->string('nombre_completo');
            $table->string('tipo_documento', 50)->nullable();
            $table->string('numero_documento', 100);
            $table->string('usuario_servicio', 100)->nullable()
                ->comment('Codigo USUARIO del Excel; no es users.id');
            $table->string('estado', 30)->default('borrador');
            $table->text('observacion')->nullable();
            $table->timestamps();

            // El evento puede repetirse para una misma persona: no es un nuevo cliente por cada fila.
            $table->index('numero_documento');
            $table->index(['id_arqueo', 'fecha_enrolamiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolamientos');
    }
};

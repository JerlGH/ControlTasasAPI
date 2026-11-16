<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios_dhl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_cliente')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->date('fecha_envio')->nullable();
            $table->string('fecha_original', 100)->nullable();
            // Texto para conservar ceros iniciales y el formato de la guia del documento fuente.
            // Se indexa sin imponer unicidad: los Excel tienen repeticiones que requieren verificacion.
            $table->string('guia', 100);
            $table->string('nombre_remitente')->nullable();
            $table->string('nombre_beneficiario')->nullable();
            $table->string('nombre_contacto')->nullable()
                ->comment('Nombre sin rol identificado en la hoja DHL de enrolamientos');
            $table->string('telefono_contacto', 50)->nullable();
            $table->string('tipo', 30)->nullable()->comment('DOC o PAQ; conservar valores originales para revision');
            $table->text('descripcion')->nullable();
            $table->string('destino')->nullable();
            $table->string('estado_envio', 100)->nullable();
            $table->string('confirmacion_original', 100)->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->string('fecha_entrega_original', 150)->nullable();
            $table->string('estado', 30)->default('registrado');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index('guia');
            $table->index(['id_arqueo', 'fecha_envio']);
            $table->index(['estado_envio', 'fecha_envio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_dhl');
    }
};

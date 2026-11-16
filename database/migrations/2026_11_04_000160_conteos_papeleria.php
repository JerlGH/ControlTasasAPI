<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteos_papeleria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_subagencia')->nullable()
                ->constrained('subagencias')->restrictOnDelete();
            $table->foreignId('id_arqueo')->nullable()->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_servicio')->nullable()
                ->constrained('servicios_caja')->restrictOnDelete();
            // La hoja PAPELERIA no identifica fecha ni si son existencias o consumos.
            $table->date('fecha')->nullable();
            $table->string('concepto', 100);
            $table->unsignedInteger('cantidad');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['id_usuario', 'fecha']);
            $table->index(['id_arqueo', 'concepto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conteos_papeleria');
    }
};

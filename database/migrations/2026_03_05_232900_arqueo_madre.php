<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqueo_madre', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('id_subagencia')->nullable()->constrained('subagencias')->restrictOnDelete();
            $table->string('alcance', 60);
            $table->unsignedBigInteger('version');
            $table->decimal('tasa_referencia', 12, 6);
            $table->json('usuarios_esperados');
            $table->enum('estado', ['borrador', 'cerrado', 'anulado'])->default('borrador');
            $table->json('totales')->nullable();
            $table->dateTime('cerrado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('id_usuario_registro')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fecha', 'alcance', 'version'], 'fin_0_u0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueo_madre');
    }
};

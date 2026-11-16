<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->restrictOnDelete();
            $table->foreignId('id_arqueo')->nullable()->constrained('arqueo')->restrictOnDelete();
            $table->string('entidad', 100);
            $table->unsignedBigInteger('id_registro');
            $table->string('accion', 30);
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->text('motivo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['id_arqueo', 'created_at']);
            $table->index(['entidad', 'id_registro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_caja');
    }
};

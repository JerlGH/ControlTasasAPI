<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqueo_cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->foreignId('id_cheque');
            $table->enum('momento', ['apertura', 'cierre']);
            // Fotografia del principal en custodia; la ganancia se registra una sola vez en cheques.
            $table->enum('moneda', ['NIO', 'USD']);
            $table->decimal('monto', 18, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['id_arqueo', 'id_cheque', 'momento'], 'arqueo_cheque_momento_unique');
            $table->index(['id_arqueo', 'momento', 'moneda']);
            $table->foreign(['id_cheque', 'moneda'], 'custodia_cheque_moneda_foreign')
                ->references(['id', 'moneda'])->on('cheques')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueo_cheques');
    }
};

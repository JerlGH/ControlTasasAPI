<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_arqueo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_arqueo')->constrained('arqueo')->restrictOnDelete();
            $table->string('estado', 30)->default('borrador');
            $table->enum('moneda', ['NIO', 'USD']);
            // Solo efectivo por denominaciones. Los cheques se cuentan en arqueo_cheques.
            $table->decimal('total', 18, 2)->nullable();
            $table->unsignedInteger('mil')->default(0);
            $table->unsignedInteger('quinientos')->default(0);
            $table->unsignedInteger('doscientos')->default(0);
            $table->unsignedInteger('cien')->default(0);
            $table->unsignedInteger('cincuenta')->default(0);
            $table->unsignedInteger('veinte')->default(0);
            $table->unsignedInteger('diez')->default(0);
            $table->unsignedInteger('cinco')->default(0);
            $table->unsignedInteger('dos')->default(0);
            $table->unsignedInteger('uno')->default(0);
            $table->dateTime('contado_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['id_arqueo', 'moneda']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_arqueo');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_arqueo');
            $table->foreign('id_arqueo')->references('id')->on('arqueo');
            $table->unsignedBigInteger('id_usuario');
            $table->foreign('id_usuario')->references('id')->on('users');
            
            $table->string('estado');
            $table->string('tipo');
            $table->string('moneda');
            $table->float('monto');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pases');
    }
};

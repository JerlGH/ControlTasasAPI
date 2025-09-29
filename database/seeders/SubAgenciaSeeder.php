<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubAgencia;

class SubAgenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subagencia = new SubAgencia();
        $subagencia->nombre = "Rio Blanco II";
        $subagencia->ubicacion = "Rio Blanco";
        $subagencia->fecha_apertura = "2025-01-01";
        $subagencia->fecha_cierre = "2025-01-01";
        $subagencia->direccion = "Gallo mas gallo 2c al sur, frente a Veterinaria Bo. Luis Alfonzo Velazques";
        $subagencia->estado_agencia = "Activo";
        $subagencia->save();
    }
}

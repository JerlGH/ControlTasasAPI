<?php

namespace Database\Seeders;

use App\Models\Trabajador;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class TrabajadorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $trabajador = new Trabajador();
        $trabajador->nombre = "Jerling Godbin García Hernández";
        $trabajador->cedula = "441-301197-0002G";
        $trabajador->fecha_nacimiento = "1997-11-28";
        $trabajador->inss = "No definido";
        $trabajador->nombre_banco = "LAFISE";
        $trabajador->numero_cuenta_banco = "No definido";
        $trabajador->estadotrabajador = "Activo";
        $trabajador->fecha_contratacion = "2025-1-1";
        $trabajador->id_subagencia = "1";
        $trabajador->cargo = "Administrador";
        $trabajador->salario_basico_inicial = "4,000.00";
        $trabajador->salario_basico_actual = "4,000.00";
        $trabajador->fecha_finalizacion = "2025-1-1";
        $trabajador->concepto_finalizacion = "Ninguno";
        $trabajador->save();
    }
}

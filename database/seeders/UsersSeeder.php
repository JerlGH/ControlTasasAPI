<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = new User();
        $user->usuario = "JerlingRoot";
        $user->contraseña = Hash::make("8246jerling");
        $user->correo_asignado_cajero = "Ninguno";
        $user->usuario_asignado_airpak = "Ninguno";
        $user->contraseña_asignada_airpak = "Ninguno";
        $user->estado_usuario = "Activo";
        $user->id_trabajador = "1";
        $user->save();
    }
}

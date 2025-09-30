<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    //
    public function index(){
        $usuarios = User::all();
        
        if($usuarios->isEmpty())
        {
            $data = 
            [
                'Mensaje'=> 'No hay usuarios registrados',
                'Status' => 200 
            ];
            return response()->json($data, 200);
        }
        
        $data=[
            'Usuarios'=>$usuarios,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }

    public function indexid($id){
        $usuario = User::find($id);

        if(!$usuario){
            $data =[
                'Mensaje' => 'No se ha encontrado este usuario',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Usuarios'=>$usuario,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }  

    public function destroy($id){
        $usuario = User::find($id);

        if(!$usuario){
            $data =[
                'Mensaje' => 'No se ha encontrado este usuario',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $usuario -> delete();

        $data =[
                'Mensaje' => 'Se ha eliminado el usuario',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $usuario = User::find($id);

        if(!$usuario){
            $data =[
                'Mensaje' => 'No se ha encontrado este usuario',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $validator = validator::make($request->all(),
        [
            'usuario'=> 'required',
            'contraseña'=> 'required',
            'rol_usuario'=> 'required',
            'correo_asignado_cajero'=> 'required',
            'usuario_asignado_airpak'=> 'required',
            'contraseña_asignada_airpak'=> 'required',
            'estado_usuario'=> 'required',
            'id_trabajador'=> 'required'
        ]);

        if ($validator->fails())
        {
            $data =
            [
                'Mensaje'=>'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400
            ];
            return response()->json($data,400);
        }

        $usuario->usuario = $request->usuario;
        $usuario->contraseña = $request->contraseña;
        $usuario->rol_usuario = $request->rol_usuario;
        $usuario->correo_asignado_cajero = $request->correo_asignado_cajero;
        $usuario->usuario_asignado_airpak = $request->usuario_asignado_airpak;
        $usuario->contraseña_asignada_airpak = $request->contraseña_asignada_airpak;
        $usuario->estado_usuario = $request->estado_usuario;
        $usuario->id_trabajador = $request->id_trabajador;
        $usuario->save();

        $data = [
            'Mensaje'=>'Se ha actualizado el usuario correctamente',
            'Usuario' => $usuario,
            'Status' => 200
        ];

        return response()->json($data,200);
    }





}

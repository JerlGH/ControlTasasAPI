<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use \stdClass;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;



class AuthController extends Controller
{
    



     public function store(Request $request){
        $validator = validator::make($request->all(),
        [
            'usuario' => 'required|unique:users,usuario',
            'contraseña'=> 'required',
            'rol_usuario'=> 'required',
            'correo_asignado_cajero'=> 'required',
            'usuario_asignado_airpak'=> 'required',
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

        $usuario = User::create([
            'usuario'=> $request->usuario,
            'contraseña' => Hash::make($request->contraseña),
            'rol_usuario'=> $request->rol_usuario,
            'correo_asignado_cajero'=> $request->correo_asignado_cajero,
            'usuario_asignado_airpak'=> $request->usuario_asignado_airpak,
            'estado_usuario'=> $request->estado_usuario,
            'id_trabajador'=> $request->id_trabajador
        ]);

        if (!$usuario){
            $data = [
                'Mensaje' => 'Error al crear el usuario',
                'Status' => 500
            ];
            return response()->json($data,500);

        }
        
        $token = $usuario->createToken('auth_token')->plainTextToken;
        $data =[
            'Usuario'=>$usuario,
            'Status' => 201
        ];
        
        return response()->json([
            'data' => $data,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }


    public function login(Request $request){

        $validator = validator::make($request->all(),
        [
            'usuario' => 'required',
            'contraseña'=> 'required'
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

        $usuario = User::where('usuario', $request['usuario'])->firstOrFail();

        if (!$usuario || !Hash::check($request['contraseña'], $usuario->contraseña)) {
            $data = [
                'Mensaje' => 'Usuario o Contraseña incorrecta',
                'Status' => 401
            ];
          return response()->json($data, 401);
        }

        $usuario = User::where('usuario', $request['usuario'])->firstOrFail();
        $token = $usuario->createToken('auth_token')->plainTextToken;

        $data = new stdClass();
        $data->usuario = $usuario;
        $data->access_token = $token;
        $data->token_type = 'Bearer';
        $data->Status = 200;

        return response()->json($data,200);
    }

   
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                $user->tokens()->delete();

                return response()->json([
                    'Mensaje'       => 'Se cerró sesión correctamente',
                    'Status'        => 'Completado',
                    'response_code' => 200,
                ]);
            }

            return response()->json([
                'Mensaje'       => 'Usario no autenticado',
                'Status'        => 'Error',
                'response_code' => 401,
            ], 401);
        } catch (\Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());

            return response()->json([
                'Mensaje'       => 'Há ocurrido un error al cerrar sesión',
                'Status'        => 'Error',
                'response_code' => 500,
            ], 500);
        }
    }

}

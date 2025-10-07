<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{

    public function index(){
        $clientes = Cliente::all();
        if($clientes->isEmpty())
        {
            $data = 
            [
                'Mensaje'=> 'No hay clientes registrados',
                'Status' => 200 
            ];
            return response()->json($data, 200);
        }
        else
        {
            $data=
            [
                'Clientes'=>$clientes,
                'Status' => 200
            ];
        }
        return response()->json($data, 200);
    }

    public function indexid($id){
        $cliente = Cliente::find($id);

        if(!$cliente){
            $data =[
                'Mensaje' => 'No se ha encontrado este cliente',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Cliente'=>$cliente,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }

    public function indexcedula($cedula) 
    {
    $cliente = Cliente::where('cedula', $cedula)->first();

    if (!$cliente) {
        $data = [
            'Mensaje' => 'No se ha encontrado este cliente',
            'Status' => 404
        ];
        return response()->json($data, 404);
    }

    $data = [
        'Cliente' => $cliente,
        'Status' => 200
    ];

    return response()->json($data, 200);
    }

    

    public function store(Request $request){
        $validator = validator::make($request->all(),
        [
            'id_usuario'=> 'required',
            'nombre'=> 'required',
            'cedula'=> 'required',
            'telefono'=> 'required',
            'direccion'=> 'required',
            'estado_cliente'=> 'required'
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

        $cliente = Cliente::create([
            'id_usuario'=> $request->id_usuario,
            'nombre'=> $request->nombre,
            'cedula'=> $request->cedula,
            'telefono'=> $request->telefono,
            'direccion'=> $request->direccion,
            'estado_cliente'=> $request->estado_cliente
        ]);
        

        if (!$cliente){
            $data = [
                'Mensaje' => 'Error al registrar el cliente',
                'Status' => 500
            ];
            return response()->json($data,500);
        }

        $data =[
            'Cliente'=> $cliente,
            'Status' => 201
        ];
        
        return response()->json($data,201);
    }

    public function destroy($id){
        $cliente = Cliente::find($id);

        if(!$cliente){
            $data =[
                'Mensaje' => 'No se ha encontrado este cliente',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $cliente -> delete();

        $data =[
                'Mensaje' => 'Se ha eliminado el cliente',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $cliente = Cliente::find($id);

        if(!$cliente){
            $data =[
                'Mensaje' => 'No se ha encontrado este cliente',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $validator = validator::make($request->all(),
        [
            'id_usuario'=> 'required',
            'nombre'=> 'required',
            'cedula'=> 'required',
            'telefono'=> 'required',
            'direccion'=> 'required',
            'estado_cliente'=> 'required'
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

        $cliente->id_usuario = $request->id_usuario;
        $cliente->nombre = $request->nombre;
        $cliente->cedula = $request->cedula;
        $cliente->telefono = $request->telefono;
        $cliente->direccion = $request->direccion;
        $cliente->estado_cliente = $request->estado_cliente;
        $cliente->save();

        $data = [
            'Mensaje'=>'Se ha actualizado este cliente correctamente',
            'Cliente' => $cliente,
            'Status' => 200
        ];

        return response()->json($data,200);
    }

}

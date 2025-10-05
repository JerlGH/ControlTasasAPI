<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mesa_cambio;
use Illuminate\Support\Facades\Validator;

class MesasCambioController extends Controller
{
    
    public function index(){
        $mesas_cambio = Mesa_cambio::all();
        if($mesas_cambio->isEmpty())
        {
            $data = 
            [
                'Mensaje'=> 'No hay mesas de cambio registrados',
                'Status' => 200 
            ];
            return response()->json($data, 200);
        }
        else
        {
            $data=
            [
                'Mesas de cambio'=>$mesas_cambio,
                'Status' => 200
            ];
        }
        return response()->json($data, 200);
    }

    public function indexid($id){
        $mesa_cambio = Mesa_cambio::find($id);

        if(!$mesa_cambio){
            $data =[
                'Mensaje' => 'No se ha encontrado esta mesa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Mesa de cambio'=> $mesa_cambio,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request){
        $validator = validator::make($request->all(),
        [
            'id_usuario'=> 'required',
            'id_cliente'=> 'required',
            'fecha'=> 'required',
            'tipo_mesa'=> 'required',
            'precio'=> 'required',
            'monto_entrada'=> 'required',
            'monto_salida'=> 'required',
            'concepto'=> 'required',
            'archivo_adjunto'=> 'required',
            'estado_mesa'=> 'required'
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

        $mesa_cambio = Mesa_cambio::create([
            'id_usuario'=> $request->id_usuario,
            'id_cliente'=> $request->id_cliente,
            'fecha'=> $request->fecha,
            'tipo_mesa'=> $request->tipo_mesa,
            'precio'=> $request->precio,
            'monto_entrada'=> $request->monto_entrada,
            'monto_salida'=> $request->monto_salida,
            'concepto'=> $request->concepto,
            'archivo_adjunto'=> $request->archivo_adjunto,
            'estado_mesa'=> $request->estado_mesa
        ]);

        if (!$mesa_cambio){
            $data = [
                'Mensaje' => 'Error al registrar esta mesa de cambio',
                'Status' => 500
            ];
            return response()->json($data,500);
        }

        $data =[
            'Mesa de cambio'=> $mesa_cambio,
            'Status' => 201
        ];
        
        return response()->json($data,201);
    }

    public function destroy($id){
        $mesa_cambio = Mesa_cambio::find($id);

        if(!$mesa_cambio){
            $data =[
                'Mensaje' => 'No se ha encontrado esta mesa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $mesa_cambio->delete();

        $data =[
                'Mensaje' => 'Se ha eliminado esta mesa de cambio',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $mesa_cambio = Mesa_cambio::find($id);

        if(!$mesa_cambio){
            $data =[
                'Mensaje' => 'No se ha encontrado esta mesa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $validator = validator::make($request->all(),
        [
            'id_usuario'=> 'required',
            'id_cliente'=> 'required',
            'fecha'=> 'required',
            'tipo_mesa'=> 'required',
            'precio'=> 'required',
            'monto_entrada'=> 'required',
            'monto_salida'=> 'required',
            'concepto'=> 'required',
            'archivo_adjunto'=> 'required',
            'estado_mesa'=> 'required'
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

        $mesa_cambio->id_usuario = $request->id_usuario;
        $mesa_cambio->id_cliente = $request->id_cliente;
        $mesa_cambio->fecha = $request->fecha;
        $mesa_cambio->tipo_mesa = $request->tipo_mesa;
        $mesa_cambio->precio = $request->precio;
        $mesa_cambio->monto_entrada = $request->monto_entrada;
        $mesa_cambio->monto_salida = $request->monto_salida;
        $mesa_cambio->concepto = $request->concepto;
        $mesa_cambio->archivo_adjunto = $request->archivo_adjunto;
        $mesa_cambio->estado_mesa = $request->estado_mesa;
        $mesa_cambio->save();

        $data = [
            'Mensaje'=>'Se ha actualizado esta mesa de cambio correctamente',
            'Mesa de cambio' => $mesa_cambio,
            'Status' => 200
        ];

        return response()->json($data,200);
    }


}

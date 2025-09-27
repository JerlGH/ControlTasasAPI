<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tasa_realizada;
use Illuminate\Support\Facades\Validator;

class TasaRealizadasController extends Controller
{
    
    public function index(){
        $tasas_realizadas = Tasa_realizada::all();
        if($tasas_realizadas->isEmpty())
        {
            $data = 
            [
                'Mensaje'=> 'No hay tasas de cambio registrados',
                'Status' => 200 
            ];
            return response()->json($data, 200);
        }
        else
        {
            $data=
            [
                'Tasas de cambio'=>$tasas_realizadas,
                'Status' => 200
            ];
        }
        return response()->json($data, 200);
    }

    public function indexid($id){
        $tasas_realizadas = Tasa_realizada::find($id);

        if(!$tasas_realizadas){
            $data =[
                'Mensaje' => 'No se ha encontrado esta tasa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Tasa de cambio'=>$tasas_realizadas,
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
            'tipo_tasa'=> 'required',
            'precio'=> 'required',
            'monto_entrada'=> 'required',
            'monto_salida'=> 'required',
            'concepto'=> 'required',
            'archivo_adjunto'=> 'required',
            'estado_tasa'=> 'required'
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

        $tasas_realizadas = Tasa_realizada::create([
            'id_usuario'=> $request->id_usuario,
            'id_cliente'=> $request->id_cliente,
            'fecha'=> $request->fecha,
            'tipo_tasa'=> $request->tipo_tasa,
            'precio'=> $request->precio,
            'monto_entrada'=> $request->monto_entrada,
            'monto_salida'=> $request->monto_salida,
            'concepto'=> $request->concepto,
            'archivo_adjunto'=> $request->archivo_adjunto,
            'estado_tasa'=> $request->estado_tasa
        ]);

        if (!$tasas_realizadas){
            $data = [
                'Mensaje' => 'Error al registrar esta tasa de cambio',
                'Status' => 500
            ];
            return response()->json($data,500);
        }

        $data =[
            'Tasa de cambio'=> $tasas_realizadas,
            'Status' => 201
        ];
        
        return response()->json($data,201);
    }

    public function destroy($id){
        $tasas_realizadas = Tasa_realizada::find($id);

        if(!$tasas_realizadas){
            $data =[
                'Mensaje' => 'No se ha encontrado este tasa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $tasas_realizadas -> delete();

        $data =[
                'Mensaje' => 'Se ha eliminado esta tasa de cambio',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $tasas_realizadas = Tasa_realizada::find($id);

        if(!$tasas_realizadas){
            $data =[
                'Mensaje' => 'No se ha encontrado este trabajador',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $validator = validator::make($request->all(),
        [
            'id_usuario'=> 'required',
            'id_cliente'=> 'required',
            'fecha'=> 'required',
            'tipo_tasa'=> 'required',
            'precio'=> 'required',
            'monto_entrada'=> 'required',
            'monto_salida'=> 'required',
            'concepto'=> 'required',
            'archivo_adjunto'=> 'required',
            'estado_tasa'=> 'required'
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

        $tasas_realizadas->id_usuario = $request->id_usuario;
        $tasas_realizadas->id_cliente = $request->id_cliente;
        $tasas_realizadas->fecha = $request->fecha;
        $tasas_realizadas->tipo_tasa = $request->tipo_tasa;
        $tasas_realizadas->precio = $request->precio;
        $tasas_realizadas->monto_entrada = $request->monto_entrada;
        $tasas_realizadas->monto_salida = $request->monto_salida;
        $tasas_realizadas->concepto = $request->concepto;
        $tasas_realizadas->archivo_adjunto = $request->archivo_adjunto;
        $tasas_realizadas->estado_tasa = $request->estado_tasa;
        $tasas_realizadas->save();

        $data = [
            'Mensaje'=>'Se ha actualizado esta tasa de cambio correctamente',
            'Tasa de cambio realizada' => $tasas_realizadas,
            'Status' => 200
        ];

        return response()->json($data,200);
    }


}

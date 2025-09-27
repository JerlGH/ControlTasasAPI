<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubAgencia;
use Illuminate\Support\Facades\Validator;

class SubAgenciaController extends Controller
{
    

    public function index(){
        $subagencia = SubAgencia::all();
        if($subagencia->isEmpty())
        {
            $data = 
            [
                'Mensaje'=> 'No hay SubAgencias registradas',
                'Status' => 200 
            ];
            return response()->json($data, 200);
        }
        else
        {
            $data=
            [
                'SubAgencia'=>$subagencia,
                'Status' => 200
            ];
        }
        return response()->json($data, 200);
    }

    public function indexid($id){
        $subagencia = SubAgencia::find($id);

        if(!$subagencia){
            $data =[
                'Mensaje' => 'No se ha encontrado esta subagencia',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Sub Agencia'=>$subagencia,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request){
        $validator = validator::make($request->all(),
        [
            'nombre'=> 'required',
            'ubicacion'=> 'required',
            'fecha_apertura'=> 'required',
            'fecha_cierre'=> 'required',
            'direccion'=> 'required',
            'estado_agencia'=> 'required'
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

        $subagencia = SubAgencia::create([
            'nombre'=> $request->nombre,
            'ubicacion'=> $request->ubicacion,
            'fecha_apertura'=> $request->fecha_apertura,
            'fecha_cierre'=> $request->fecha_cierre,
            'direccion'=> $request->direccion,
            'estado_agencia'=> $request->estado_agencia
        ]);

        if (!$subagencia){
            $data = [
                'Mensaje' => 'Error al registrar la SubAgencia',
                'Status' => 500
            ];
            return response()->json($data,500);
        }

        $data =[
            'Sub Agencia'=> $subagencia,
            'Status' => 201
        ];
        
        return response()->json($data,201);
    }

    public function destroy($id){
        $subagencia = SubAgencia::find($id);

        if(!$subagencia){
            $data =[
                'Mensaje' => 'No se ha encontrado este subagencia',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $subagencia -> delete();

        $data =[
                'Mensaje' => 'Se ha eliminado esta subagencia',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $subagencia = SubAgencia::find($id);

        if(!$subagencia){
            $data =[
                'Mensaje' => 'No se ha encontrado esta subagencia',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $validator = validator::make($request->all(),
        [
            'nombre'=> 'required',
            'ubicacion'=> 'required',
            'fecha_apertura'=> 'required',
            'fecha_cierre'=> 'required',
            'direccion'=> 'required',
            'estado_agencia'=> 'required'
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

        $subagencia->nombre = $request->nombre;
        $subagencia->ubicacion = $request->ubicacion;
        $subagencia->fecha_apertura = $request->fecha_apertura;
        $subagencia->fecha_cierre = $request->fecha_cierre;
        $subagencia->direccion = $request->direccion;
        $subagencia->estado_agencia = $request->estado_agencia;
        $subagencia->save();

        $data = [
            'Mensaje'=>'Se ha actualizado esta subagencia correctamente',
            'Sub Agencia' => $subagencia,
            'Status' => 200
        ];

        return response()->json($data,200);
    }

}

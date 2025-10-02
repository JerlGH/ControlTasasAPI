<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trabajador;
use Illuminate\Support\Facades\Validator;

class TrabajadorController extends Controller{
    public function index(){
        $trabajador = Trabajador::all();
        if($trabajador->isEmpty())
        {
            $data = 
            [
                'Mensaje'=> 'No hay trabajadores registrados',
                'Status' => 200 
            ];
            return response()->json($data, 200);
        }
        else
        {
            $data=
            [
                'Trabajadores'=>$trabajador,
                'Status' => 200
            ];
        }
        return response()->json($data, 200);
    }

    public function indexid($id){
        $trabajador = Trabajador::find($id);

        if(!$trabajador){
            $data =[
                'Mensaje' => 'No se ha encontrado este trabajador',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Trabajador'=>$trabajador,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request){
        $validator = validator::make($request->all(),
        [
            'nombre'=> 'required',
            'cedula'=> 'required',
            'fecha_nacimiento'=> 'required',
            'inss'=> 'required',
            'nombre_banco'=> 'required',
            'numero_cuenta_banco'=> 'required',
            'estadotrabajador'=> 'required',
            'fecha_contratacion'=> 'required',
            'id_subagencia'=> 'required',
            'cargo'=> 'required',
            'salario_basico_inicial'=> 'required',
            'salario_basico_actual'=> 'required',
            'fecha_finalizacion'=> 'required',
            'concepto_finalizacion'=> 'required'
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

        $trabajador = Trabajador::create([


            'nombre'=> $request->nombre,
            'cedula'=> $request->cedula,
            'fecha_nacimiento'=> $request->fecha_nacimiento,
            'inss'=> $request->inss,
            'nombre_banco'=> $request->nombre_banco,
            'numero_cuenta_banco'=> $request->numero_cuenta_banco,
            'estadotrabajador'=> $request->estadotrabajador,
            'fecha_contratacion'=> $request->fecha_contratacion,
            'id_subagencia'=> $request->id_subagencia,
            'cargo'=> $request->cargo,
            'salario_basico_inicial'=> $request->salario_basico_inicial,
            'salario_basico_actual'=> $request->salario_basico_actual,
            'fecha_finalizacion'=> $request->fecha_finalizacion,
            'concepto_finalizacion'=> $request->concepto_finalizacion
        ]);

        if (!$trabajador){
            $data = [
                'Mensaje' => 'Error al registrar el trabajador',
                'Status' => 500
            ];
            return response()->json($data,500);
        }

        $data =[
            'Trabajador'=> $trabajador,
            'Status' => 201
        ];
        
        return response()->json($data,201);
    }

    public function destroy($id){
        $trabajador = Trabajador::find($id);

        if(!$trabajador){
            $data =[
                'Mensaje' => 'No se ha encontrado este trabajador',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $trabajador -> delete();

        $data =[
                'Mensaje' => 'Se ha eliminado el trabajador',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $trabajador = Trabajador::find($id);

        if(!$trabajador){
            $data =[
                'Mensaje' => 'No se ha encontrado este trabajador',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $validator = validator::make($request->all(),
        [
            'nombre'=> 'required',
            'cedula'=> 'required',
            'fecha_nacimiento'=> 'required',
            'inss'=> 'required',
            'nombre_banco'=> 'required',
            'numero_cuenta_banco'=> 'required',
            'estadotrabajador'=> 'required',
            'fecha_contratacion'=> 'required',
            'id_subagencia'=> 'required',
            'cargo'=> 'required',
            'salario_basico_inicial'=> 'required',
            'salario_basico_actual'=> 'required',
            'fecha_finalizacion'=> 'required',
            'concepto_finalizacion'=> 'required'
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
        $trabajador->nombre = $request->nombre;
        $trabajador->cedula = $request->cedula;
        $trabajador->fecha_nacimiento = $request->fecha_nacimiento;
        $trabajador->inss = $request->inss;
        $trabajador->nombre_banco = $request->nombre_banco;
        $trabajador->numero_cuenta_banco = $request->numero_cuenta_banco;
        $trabajador->estadotrabajador = $request->estadotrabajador;
        $trabajador->fecha_contratacion = $request->fecha_contratacion;
        $trabajador->id_subagencia = $request->id_subagencia;
        $trabajador->cargo = $request->cargo;
        $trabajador->salario_basico_inicial = $request->salario_basico_inicial;
        $trabajador->salario_basico_actual = $request->salario_basico_actual;
        $trabajador->fecha_finalizacion = $request->fecha_finalizacion;
        $trabajador->concepto_finalizacion = $request->concepto_finalizacion;
        $trabajador->save();

        $data = [
            'Mensaje'=>'Se ha actualizado este trabajador correctamente',
            'Trabajador' => $trabajador,
            'Status' => 200
        ];

        return response()->json($data,200);
    }

}

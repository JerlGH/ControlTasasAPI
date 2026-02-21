<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mesa_De_Cambio;
use Illuminate\Support\Facades\Validator;

class Mesa_de_CambioController extends Controller
{
    public function index(){
        // Obtener todas las mesas de cambio
        $mesas_de_cambio = Mesa_De_Cambio::all();
        if($mesas_de_cambio->isEmpty())
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
                'Mesas de cambio'=>$mesas_de_cambio,
                'Status' => 200
            ];
        }
        return response()->json($data, 200);
    }

    public function show($id){
        // Obtener una mesa de cambio por su ID
        $mesa_de_cambio = Mesa_De_Cambio::find($id);

        if(!$mesa_de_cambio){
            $data =[
                'Mensaje' => 'No se ha encontrado esta mesa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $data=[
            'Mesa de cambio'=> $mesa_de_cambio,
            'Status' => 200
        ];

        return response()->json($data, 200);
    }

    public function store(Request $request){
        // Crear una nueva mesa de cambio
        $validator = validator::make($request->all(),
        [
            'id_usuario'=> 'required',
            'id_cliente'=> 'required',
            'fecha'=> 'required',
            'tipo_transaccion'=> 'required',
            'tasa'=> 'required',
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

        $mesa_de_cambio = Mesa_De_Cambio::create([
            'id_usuario'=> $request->id_usuario,
            'id_cliente'=> $request->id_cliente,
            'fecha'=> $request->fecha,
            'tipo_transaccion'=> $request->tipo_transaccion,
            'tasa'=> $request->tasa,
            'monto_entrada'=> $request->monto_entrada,
            'monto_salida'=> $request->monto_salida,
            'concepto'=> $request->concepto,
            'archivo_adjunto'=> $request->archivo_adjunto,
            'estado_mesa'=> $request->estado_mesa
        ]);

        if (!$mesa_de_cambio){
            $data = [
                'Mensaje' => 'Error al registrar esta mesa de cambio',
                'Status' => 500
            ];
            return response()->json($data,500);
        }

        $data =[
            'Mesa de cambio'=> $mesa_de_cambio,
            'Status' => 201
        ];
        
        return response()->json($data,201);
    }

    public function update(Request $request, $id)
    {
        // Actualizar una mesa de cambio existente
        $mesa_de_cambio = Mesa_de_Cambio::find($id);

        if(!$mesa_de_cambio){
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
            'fecha',
            'tipo_transaccion',
            'tasa'=> 'required',
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

        $mesa_de_cambio->id_usuario = $request->id_usuario;
        $mesa_de_cambio->id_cliente = $request->id_cliente;
        if ($request->filled('fecha'))
        {
            $mesa_de_cambio->fecha = $request->fecha;
        }
        if ($request->filled('tipo_transaccion'))
        {
            $mesa_de_cambio->tipo_transaccion = $request->tipo_transaccion;
        }

        $mesa_de_cambio->tasa = $request->tasa;
        $mesa_de_cambio->monto_entrada = $request->monto_entrada;
        $mesa_de_cambio->monto_salida = $request->monto_salida;
        $mesa_de_cambio->concepto = $request->concepto;
        $mesa_de_cambio->archivo_adjunto = $request->archivo_adjunto;
        $mesa_de_cambio->estado_mesa = $request->estado_mesa;
        $mesa_de_cambio->save();

        $data = [
            'Mensaje'=>'Se ha actualizado esta mesa de cambio correctamente',
            'Mesa de cambio' => $mesa_de_cambio,
            'Status' => 200
        ];

        return response()->json($data,200);
    }

    public function destroy($id)
    {
        // Eliminar una mesa de cambio
        $mesa_de_cambio = Mesa_de_Cambio::find($id);

        if(!$mesa_de_cambio){
            $data =[
                'Mensaje' => 'No se ha encontrado esta mesa de cambio',
                'Status' => 404
            ];
            return response()->json($data, 404);
        }

        $mesa_de_cambio->delete();

        $data =[
                'Mensaje' => 'Se ha eliminado esta mesa de cambio',
                'Status' => 200
            ];
            return response()->json($data, 200);
    }


    public function showfiltred(Request $request){
        // Obtener mesas de cambio filtradas por estado
        $validator = Validator::make($request->all(), [
        // fecha exacta (YYYY-MM-DD) -> whereDate
        'fecha_desde'  => 'nullable|date',
        'fecha_hasta'  => 'nullable|date',
        'tipo_transaccion' => 'nullable|string',
        'estado_mesa' => 'nullable|string',
        'id_usuario' => 'nullable|integer',
        // Filtros por año y mes
        'anio' => 'nullable|integer|min:1900|max:2100',
        'mes'  => 'nullable|integer|between:1,12',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'Mensaje' => 'Error en la validación de datos',
            'Errores' => $validator->errors(),
            'Status'  => 422
        ], 422);
    }

        // Construir consulta dinámica
    $query = Mesa_De_Cambio::query();

    if ($request->filled('fecha_desde')) {
        $query->whereDate('fecha', '>=', $request->input('fecha_desde'));
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereDate('fecha', '<=', $request->input('fecha_hasta'));
    }
    if ($request->filled('id_usuario')) {
        $query->where('id_usuario', $request->input('id_usuario'));
    }
    if ($request->filled('tipo_transaccion')) {
        $query->where('tipo_transaccion', $request->input('tipo_transaccion'));
    }
    if ($request->filled('estado_mesa')) {
        $query->where('estado_mesa', $request->input('estado_mesa'));
    }
    if ($request->filled('anio')) {
        $query->whereYear('fecha', $request->input('anio'));
    }
    if ($request->filled('mes')) {
        $query->whereMonth('fecha', $request->input('mes'));
    }
    

    // Opcional: orden y paginación
    $query->orderBy('fecha', 'desc');
    $mesas_de_cambio = $query->get();

    if ($mesas_de_cambio->isEmpty()) {
        return response()->json([
            'Mensaje' => 'No se han encontrado mesas de cambio con los filtros proporcionados',
            'Status'  => 404
        ], 404);
    }

    return response()->json([
        'Mesas de cambio' => $mesas_de_cambio,
        'Status' => 200
    ], 200);
}










}

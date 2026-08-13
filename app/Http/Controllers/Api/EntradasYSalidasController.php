<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entradas_Y_Salidas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EntradasYSalidasController extends Controller
{
    public function index()
    {
        $entradasYSalidas = Entradas_Y_Salidas::all();

        if ($entradasYSalidas->isEmpty()) {
            return response()->json([
                'Mensaje' => 'No hay entradas y salidas registradas',
                'Status' => 200,
            ], 200);
        }

        return response()->json([
            'Entradas y salidas' => $entradasYSalidas,
            'Status' => 200,
        ], 200);
    }

    public function indexid($id)
    {
        $entradaSalida = Entradas_Y_Salidas::find($id);

        if (!$entradaSalida) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado esta entrada/salida',
                'Status' => 404,
            ], 404);
        }

        return response()->json([
            'Entrada salida' => $entradaSalida,
            'Status' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_arqueo' => 'required|integer|exists:arqueo,id',
            'estado' => 'required|string',
            'tipo' => 'required|string',
            'moneda' => 'required|string',
            'monto' => 'required|numeric',
            'observacion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $entradaSalida = Entradas_Y_Salidas::create($request->all());

        if (!$entradaSalida) {
            return response()->json([
                'Mensaje' => 'Error al registrar la entrada/salida',
                'Status' => 500,
            ], 500);
        }

        return response()->json([
            'Entrada salida' => $entradaSalida,
            'Status' => 201,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $entradaSalida = Entradas_Y_Salidas::find($id);

        if (!$entradaSalida) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado esta entrada/salida',
                'Status' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_arqueo' => 'required|integer|exists:arqueo,id',
            'estado' => 'required|string',
            'tipo' => 'required|string',
            'moneda' => 'required|string',
            'monto' => 'required|numeric',
            'observacion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $entradaSalida->fill($request->all());
        $entradaSalida->save();

        return response()->json([
            'Mensaje' => 'Se ha actualizado esta entrada/salida correctamente',
            'Entrada salida' => $entradaSalida,
            'Status' => 200,
        ], 200);
    }

    public function destroy($id)
    {
        $entradaSalida = Entradas_Y_Salidas::find($id);

        if (!$entradaSalida) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado esta entrada/salida',
                'Status' => 404,
            ], 404);
        }

        $entradaSalida->delete();

        return response()->json([
            'Mensaje' => 'Se ha eliminado la entrada/salida',
            'Status' => 200,
        ], 200);
    }
}

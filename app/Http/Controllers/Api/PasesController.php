<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PasesController extends Controller
{
    public function index()
    {
        $pases = Pases::all();

        if ($pases->isEmpty()) {
            return response()->json([
                'Mensaje' => 'No hay pases registrados',
                'Status' => 200,
            ], 200);
        }

        return response()->json([
            'Pases' => $pases,
            'Status' => 200,
        ], 200);
    }

    public function indexid($id)
    {
        $pase = Pases::find($id);

        if (!$pase) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este pase',
                'Status' => 404,
            ], 404);
        }

        return response()->json([
            'Pase' => $pase,
            'Status' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_arqueo' => 'required|integer|exists:arqueo,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'estado' => 'required|string',
            'tipo' => 'required|string',
            'moneda' => 'required|string',
            'monto' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $pase = Pases::create($request->all());

        if (!$pase) {
            return response()->json([
                'Mensaje' => 'Error al registrar el pase',
                'Status' => 500,
            ], 500);
        }

        return response()->json([
            'Pase' => $pase,
            'Status' => 201,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $pase = Pases::find($id);

        if (!$pase) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este pase',
                'Status' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_arqueo' => 'required|integer|exists:arqueo,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'estado' => 'required|string',
            'tipo' => 'required|string',
            'moneda' => 'required|string',
            'monto' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $pase->fill($request->all());
        $pase->save();

        return response()->json([
            'Mensaje' => 'Se ha actualizado este pase correctamente',
            'Pase' => $pase,
            'Status' => 200,
        ], 200);
    }

    public function destroy($id)
    {
        $pase = Pases::find($id);

        if (!$pase) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este pase',
                'Status' => 404,
            ], 404);
        }

        $pase->delete();

        return response()->json([
            'Mensaje' => 'Se ha eliminado el pase',
            'Status' => 200,
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cheques;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChequesController extends Controller
{
    public function index()
    {
        $cheques = Cheques::all();

        if ($cheques->isEmpty()) {
            return response()->json([
                'Mensaje' => 'No hay cheques registrados',
                'Status' => 200,
            ], 200);
        }

        return response()->json([
            'Cheques' => $cheques,
            'Status' => 200,
        ], 200);
    }

    public function indexid($id)
    {
        $cheque = Cheques::find($id);

        if (!$cheque) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este cheque',
                'Status' => 404,
            ], 404);
        }

        return response()->json([
            'Cheque' => $cheque,
            'Status' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_arqueo' => 'required|integer|exists:arqueo,id',
            'estado' => 'required|string',
            'moneda' => 'required|string',
            'monto' => 'required|numeric',
            'ganancia' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $cheque = Cheques::create($request->all());

        if (!$cheque) {
            return response()->json([
                'Mensaje' => 'Error al registrar el cheque',
                'Status' => 500,
            ], 500);
        }

        return response()->json([
            'Cheque' => $cheque,
            'Status' => 201,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cheque = Cheques::find($id);

        if (!$cheque) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este cheque',
                'Status' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_arqueo' => 'required|integer|exists:arqueo,id',
            'estado' => 'required|string',
            'moneda' => 'required|string',
            'monto' => 'required|numeric',
            'ganancia' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $cheque->fill($request->all());
        $cheque->save();

        return response()->json([
            'Mensaje' => 'Se ha actualizado este cheque correctamente',
            'Cheque' => $cheque,
            'Status' => 200,
        ], 200);
    }

    public function destroy($id)
    {
        $cheque = Cheques::find($id);

        if (!$cheque) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este cheque',
                'Status' => 404,
            ], 404);
        }

        $cheque->delete();

        return response()->json([
            'Mensaje' => 'Se ha eliminado el cheque',
            'Status' => 200,
        ], 200);
    }
}

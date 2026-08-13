<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Arqueo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArqueoController extends Controller
{
    public function index()
    {
        $arqueos = Arqueo::all();

        if ($arqueos->isEmpty()) {
            return response()->json([
                'Mensaje' => 'No hay arqueos registrados',
                'Status' => 200,
            ], 200);
        }

        return response()->json([
            'Arqueos' => $arqueos,
            'Status' => 200,
        ], 200);
    }

    public function indexid($id)
    {
        $arqueo = Arqueo::find($id);

        if (!$arqueo) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este arqueo',
                'Status' => 404,
            ], 404);
        }

        return response()->json([
            'Arqueo' => $arqueo,
            'Status' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|integer|exists:users,id',
            'estado' => 'required|string',
            'fecha' => 'required|date',
            'caja_inicial_cordobas' => 'required|numeric',
            'caja_final_cordobas' => 'required|numeric',
            'caja_inicial_dolares' => 'required|numeric',
            'caja_final_dolares' => 'required|numeric',
            'biller_cordobas' => 'required|numeric',
            'biller_dolares' => 'required|numeric',
            'envios_cordobas' => 'required|numeric',
            'envios_dolares' => 'required|numeric',
            'pagos_cordobas' => 'required|numeric',
            'pagos_dolares' => 'required|numeric',
            'cantidad_cheques' => 'required|numeric',
            'monto_total_cheques' => 'required|numeric',
            'ganancia_total_cheques' => 'required|numeric',
            'diferencia_cordobas' => 'required|numeric',
            'diferencia_dolares' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $arqueo = Arqueo::create($request->all());

        if (!$arqueo) {
            return response()->json([
                'Mensaje' => 'Error al registrar el arqueo',
                'Status' => 500,
            ], 500);
        }

        return response()->json([
            'Arqueo' => $arqueo,
            'Status' => 201,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $arqueo = Arqueo::find($id);

        if (!$arqueo) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este arqueo',
                'Status' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|integer|exists:users,id',
            'estado' => 'required|string',
            'fecha' => 'required|date',
            'caja_inicial_cordobas' => 'required|numeric',
            'caja_final_cordobas' => 'required|numeric',
            'caja_inicial_dolares' => 'required|numeric',
            'caja_final_dolares' => 'required|numeric',
            'biller_cordobas' => 'required|numeric',
            'biller_dolares' => 'required|numeric',
            'envios_cordobas' => 'required|numeric',
            'envios_dolares' => 'required|numeric',
            'pagos_cordobas' => 'required|numeric',
            'pagos_dolares' => 'required|numeric',
            'cantidad_cheques' => 'required|numeric',
            'monto_total_cheques' => 'required|numeric',
            'ganancia_total_cheques' => 'required|numeric',
            'diferencia_cordobas' => 'required|numeric',
            'diferencia_dolares' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $arqueo->fill($request->all());
        $arqueo->save();

        return response()->json([
            'Mensaje' => 'Se ha actualizado este arqueo correctamente',
            'Arqueo' => $arqueo,
            'Status' => 200,
        ], 200);
    }

    public function destroy($id)
    {
        $arqueo = Arqueo::find($id);

        if (!$arqueo) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este arqueo',
                'Status' => 404,
            ], 404);
        }

        $arqueo->delete();

        return response()->json([
            'Mensaje' => 'Se ha eliminado el arqueo',
            'Status' => 200,
        ], 200);
    }
}

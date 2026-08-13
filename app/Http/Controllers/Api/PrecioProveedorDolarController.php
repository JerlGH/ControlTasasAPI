<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Precio_Proveedor_Dolar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PrecioProveedorDolarController extends Controller
{
    public function index()
    {
        $precios = Precio_Proveedor_Dolar::all();

        if ($precios->isEmpty()) {
            return response()->json([
                'Mensaje' => 'No hay precios de proveedor registrados',
                'Status' => 200,
            ], 200);
        }

        return response()->json([
            'Precios proveedor dólar' => $precios,
            'Status' => 200,
        ], 200);
    }

    public function indexid($id)
    {
        $precio = Precio_Proveedor_Dolar::find($id);

        if (!$precio) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este precio de proveedor',
                'Status' => 404,
            ], 404);
        }

        return response()->json([
            'Precio proveedor dólar' => $precio,
            'Status' => 200,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|integer|exists:users,id',
            'estado' => 'required|string',
            'proveedor_actual' => 'required|string',
            'precio_dolar' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $precio = Precio_Proveedor_Dolar::create($request->all());

        if (!$precio) {
            return response()->json([
                'Mensaje' => 'Error al registrar el precio del dólar',
                'Status' => 500,
            ], 500);
        }

        return response()->json([
            'Precio proveedor dólar' => $precio,
            'Status' => 201,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $precio = Precio_Proveedor_Dolar::find($id);

        if (!$precio) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este precio de proveedor',
                'Status' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|integer|exists:users,id',
            'estado' => 'required|string',
            'proveedor_actual' => 'required|string',
            'precio_dolar' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Mensaje' => 'Error en la validación de datos',
                'Errores' => $validator->errors(),
                'Status' => 400,
            ], 400);
        }

        $precio->fill($request->all());
        $precio->save();

        return response()->json([
            'Mensaje' => 'Se ha actualizado este precio de proveedor correctamente',
            'Precio proveedor dólar' => $precio,
            'Status' => 200,
        ], 200);
    }

    public function destroy($id)
    {
        $precio = Precio_Proveedor_Dolar::find($id);

        if (!$precio) {
            return response()->json([
                'Mensaje' => 'No se ha encontrado este precio de proveedor',
                'Status' => 404,
            ], 404);
        }

        $precio->delete();

        return response()->json([
            'Mensaje' => 'Se ha eliminado este precio de proveedor',
            'Status' => 200,
        ], 200);
    }
}

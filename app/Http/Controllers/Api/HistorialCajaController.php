<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistorialCaja;
use App\Support\Caja\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HistorialCajaController extends Controller
{
    private function query(Request $request)
    {
        $query = HistorialCaja::query();
        if (! Access::admin($request)) {
            $user = Access::user($request);
            $query->where(fn ($q) => $q->whereHas('arqueo', fn ($a) => $a->where('id_usuario', $user->id))
                ->orWhere(fn ($q) => $q->whereNull('id_arqueo')->where('id_usuario', $user->id)));
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), ['id_arqueo' => 'sometimes|integer',
            'entidad' => 'sometimes|string|max:100', 'id_registro' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1'])->validate();
        $query = $this->query($request);
        foreach (['id_arqueo', 'entidad', 'id_registro'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        $page = $query->orderByDesc('id')->paginate($data['per_page'] ?? 50, ['*'], 'page', $data['page'] ?? 1);

        return new JsonResponse(['Historial' => $page->items(), 'Paginacion' => [
            'total' => $page->total(), 'pagina' => $page->currentPage(), 'por_pagina' => $page->perPage(),
        ], 'Status' => 200]);
    }

    public function indexid(Request $request, int $id): JsonResponse
    {
        return new JsonResponse(['Historial' => $this->query($request)->findOrFail($id), 'Status' => 200]);
    }
}

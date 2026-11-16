<?php

namespace App\Http\Controllers\Api;

use App\Models\RemesaBancaria;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RemesaBancariaController extends RegistroCajaController
{
    protected string $model = RemesaBancaria::class;
    protected string $label = 'Remesa';
    protected string $plural = 'Remesas';

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), ['banco' => [$required, 'string', 'max:100'],
            'tipo' => [$required, 'in:Entrada,Salida'], 'moneda' => [$required, 'in:NIO,USD'],
            'numero_remesa' => [$required, 'string', 'max:100'], 'monto' => $this->amount($required, 2, false, true),
            'medio' => ['nullable', 'in:efectivo,cheque,transferencia,mixto'], 'fecha_operacion' => ['nullable', 'date']]);
    }
}

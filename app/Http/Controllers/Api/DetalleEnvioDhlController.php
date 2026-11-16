<?php

namespace App\Http\Controllers\Api;

use App\Models\DetalleEnvioDhl;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DetalleEnvioDhlController extends RegistroCajaController
{
    protected string $model = DetalleEnvioDhl::class;
    protected string $label = 'DetalleEnvio';
    protected string $plural = 'DetallesEnvio';
    protected string $journalRelation = 'envio.arqueo';
    protected bool $annul = false;
    protected array $immutable = ['id_envio_dhl'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return ['id_envio_dhl' => [$required, 'integer', 'exists:envios_dhl,id'], 'descripcion' => [$required, 'string', 'max:10000'],
            'cantidad' => ['nullable', 'integer', 'between:1,4294967295'], 'unidad' => ['nullable', 'string', 'max:50'],
            'peso' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/D'],
            'observacion' => ['nullable', 'string', 'max:10000']];
    }
}

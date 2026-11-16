<?php

namespace App\Http\Controllers\Api;

use App\Models\ArqueoCheque;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArqueoChequeController extends RegistroCajaController
{
    protected string $model = ArqueoCheque::class;
    protected string $label = 'Custodia';
    protected string $plural = 'Custodias';
    protected bool $annul = false;
    protected array $immutable = ['id_arqueo', 'id_cheque', 'momento'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return ['id_arqueo' => [$required, 'integer', 'exists:arqueo,id'],
            'id_cheque' => [$required, 'integer', 'exists:cheques,id'], 'momento' => [$required, 'in:apertura,cierre'],
            'moneda' => ['sometimes', 'in:NIO,USD'], 'monto' => $this->amount('sometimes', 2, false, true),
            'observacion' => ['nullable', 'string', 'max:10000']];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        Access::check($values['momento'] !== 'apertura' || ! $day->id_arqueo_anterior,
            'momento', 'La custodia de apertura trasladada no puede modificarse');
        $cheque = References::cheque($request, (int) $values['id_cheque'], $day);
        Access::check($cheque->estado !== 'anulado' && $cheque->monto !== null, 'id_cheque', 'El cheque debe estar vigente y tener principal conocido');
        if (! Access::admin($request) && $cheque->id_arqueo !== $day->id) {
            Access::check($day->custodias()->where('id_cheque', $cheque->id)->where('momento', 'apertura')->exists(),
                'id_cheque', 'El cheque debe formar parte de la apertura o registrarse en esta jornada');
        }
        Access::check(! isset($values['moneda']) || $values['moneda'] === $cheque->moneda, 'moneda', 'La moneda no coincide con el cheque');
        Access::check(! isset($values['monto']) || Decimal::compare($values['monto'], $cheque->monto) === 0,
            'monto', 'El principal contado debe coincidir con el documento');
        $duplicate = ArqueoCheque::where('id_arqueo', $day->id)->where('id_cheque', $cheque->id)->where('momento', $values['momento']);
        if ($record) {
            $duplicate->whereKeyNot($record->id);
        }
        Access::check(! $duplicate->exists(), 'id_cheque', 'El cheque ya fue contado en ese momento');
        $values['moneda'] = $cheque->moneda;
        $values['monto'] = $cheque->monto;

        return $values;
    }

    protected function deleting(Request $request, Model $record, ?Arqueo $day): void
    {
        Access::check($record->momento !== 'apertura' || ! $day->id_arqueo_anterior,
            'momento', 'La custodia trasladada debe conservarse');
    }
}

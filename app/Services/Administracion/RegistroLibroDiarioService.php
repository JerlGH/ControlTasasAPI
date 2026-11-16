<?php

namespace App\Services\Administracion;

use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\DetalleCuentaDiario;
use App\Models\Administracion\DetalleRemesaCentral;
use App\Models\Administracion\LibroDiario;
use App\Models\Administracion\OperacionFinanciera;
use App\Models\Administracion\ReporteRemesaCentral;
use App\Models\Administracion\RevisionLibroDiario;
use App\Models\Entradas_Y_Salidas;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\RecursosLibroDiario as R;
use App\Support\Caja\Access;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class RegistroLibroDiarioService
{
    public function save(Request $request, string $resource, array $input, ?Model $record = null): Model
    {
        unset($input['motivo']);
        $before = $record?->getAttributes();
        F::check(! $record instanceof RevisionLibroDiario, 'registro', 'La revision se conserva; registre otra revision');
        if ($record) {
            F::check(! in_array($record->estado, ['cerrado', 'confirmado', 'anulado'], true), 'estado', 'El registro ya no admite cambios');
            foreach ($input as $field => $value) {
                if (in_array($field, ['fecha', 'referencia', 'id_libro_diario', 'id_reporte', 'id_subagencia', 'id_cuenta', 'canal', 'id_operacion', 'id_entrada_salida', 'banco', 'moneda', 'tipo'], true)) {
                    $current = $field === 'fecha' ? F::date($record->$field) : $record->$field;
                    F::check((string) $current === (string) $value, $field, 'La identidad es inmutable; anule y registre la correccion');
                }
            }
        }
        $data = array_replace($record?->attributesToArray() ?? [], $input);
        $this->references($data);
        $root = null;
        if (isset($data['id_libro_diario'])) {
            $root = LibroDiario::whereKey($data['id_libro_diario'])->lockForUpdate()->firstOrFail();
            $this->draft($root);
        }
        if ($resource === 'librosdiarios') {
            F::openDate($data['fecha']);
            $data['usuarios_esperados'] = array_map('intval', $data['usuarios_esperados']);
            sort($data['usuarios_esperados']);
            if (! $record) {
                $data['version'] = 1 + (int) LibroDiario::whereDate('fecha', $data['fecha'])->max('version');
                $data['id_anterior'] = LibroDiarioService::previous($data['fecha'])?->id;
            }
            if (! empty($data['id_reporte_remesas'])) {
                $report = ReporteRemesaCentral::findOrFail($data['id_reporte_remesas']);
                F::check($report->estado !== 'anulado' && F::date($report->fecha) === $data['fecha'], 'id_reporte_remesas', 'Utilice el reporte de la misma fecha');
            }
        } elseif ($resource === 'reportesremesascentrales') {
            F::openDate($data['fecha']);
            F::check(F::date($data['corte_at']) === $data['fecha'], 'corte_at', 'El corte debe corresponder a la fecha del reporte');
            $data['agencias_esperadas'] = array_map('intval', $data['agencias_esperadas']);
            sort($data['agencias_esperadas']);
            if ($record) {
                F::check(! $record->detalles()->where('estado', 'registrado')->whereNotIn('id_subagencia', $data['agencias_esperadas'])->exists(), 'agencias_esperadas', 'Hay lineas de agencias que se excluirian');
            }
        } elseif ($resource === 'detallesremesascentrales') {
            $report = ReporteRemesaCentral::whereKey($data['id_reporte'])->lockForUpdate()->firstOrFail();
            $this->draft($report);
            F::check(in_array((int) $data['id_subagencia'], $report->agencias_esperadas, true), 'id_subagencia', 'La agencia no pertenece al reporte');
            $data['clave_activa'] = implode(':', [$report->id, $data['id_subagencia'], $data['banco'], $data['moneda'], $data['tipo']]);
        } elseif ($resource === 'cuentasdiario') {
            $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
            F::active($account, 'id_cuenta');
            F::check($account->tipo === ($data['canal'] === 'bancentro' ? 'banco' : 'pos'), 'id_cuenta', 'Bancentro requiere banco; La Fise/Banpro requieren cuentas POS independientes');
            F::check($account->rubro_capital === 'banco', 'id_cuenta', 'Las seis cuentas del Diario deben estar incluidas en bancos del capital');
            F::check(F::date($account->fecha_apertura) <= F::date($root->fecha), 'id_cuenta', 'La cuenta no existia en la fecha del diario');
            $data['moneda'] = $account->moneda;
            $data['clave_activa'] = $root->id.':'.$data['canal'].':'.$account->moneda;
            $data['clave_cuenta_activa'] = $root->id.':'.$account->id;
        } elseif ($resource === 'partidasdiario') {
            $this->validatePartida($root, $data);
            $data['clave_activa'] = $root->id.':'.$data['id_operacion'];
        } elseif ($resource === 'detallescuentasdiario') {
            $this->validateDetail($root, $data, $record);
            $data['clave_activa'] = $root->id.':'.$data['id_entrada_salida'];
        } elseif ($resource === 'revisionesdiario') {
            $result = (new LibroDiarioService)->calculate($root);
            $control = collect($result['controles'])->firstWhere('clave', $data['clave_control']);
            F::check($control && hash_equals($control['huella'], $data['huella']), 'huella', 'El control cambio o no existe; consulte nuevamente el resumen');
            F::check($data['resultado'] !== 'revisado' || $control['coincide'], 'resultado', 'Una diferencia requiere justificacion o queda observada');
            F::check($data['resultado'] !== 'justificado' || (! $control['coincide'] && (! empty($data['id_documento']) || ! empty($data['referencia']))), 'resultado', 'Una justificacion requiere diferencia y evidencia o referencia');
            $data['revisado_at'] = Carbon::now();
        }
        $class = R::model($resource);
        $new = $record === null;
        $record ??= new $class;
        if ($new) {
            $data['id_usuario_registro'] = Access::user($request)->id;
        }
        $record->fill($data)->save();
        F::audit($request, $record, $new ? 'crear' : 'actualizar', $before);
        if ($new && $record instanceof LibroDiario && $record->id_anterior) {
            foreach ($record->anterior->cuentas()->with('cuenta')->where('estado', 'registrado')->get() as $account) {
                $target = $account->cuenta;
                if ($target->estado === 'activo' && $target->rubro_capital === 'banco' && $target->moneda === $account->moneda
                    && $target->tipo === ($account->canal === 'bancentro' ? 'banco' : 'pos')) {
                    $this->save($request, 'cuentasdiario', ['id_libro_diario' => $record->id, 'id_cuenta' => $account->id_cuenta, 'canal' => $account->canal]);
                }
            }
        }

        return $record;
    }

    public function validatePartida(LibroDiario $root, array $data): void
    {
        $this->references($data);
        $op = OperacionFinanciera::findOrFail($data['id_operacion']);
        F::active($op, 'id_operacion');
        F::check(F::date($op->fecha) === F::date($root->fecha), 'id_operacion', 'La operacion debe pertenecer a la fecha del diario');
        $source = $op->origen_snapshot;
        if ($source) {
            $this->source($root, \App\Models\Arqueo::findOrFail($source['id_arqueo']));
        }
        $accounts = $root->cuentas()->where('estado', 'registrado')->get();
        $linked = $accounts->filter(fn ($a) => in_array($a->id_cuenta, [$op->id_cuenta_origen, $op->id_cuenta_destino], true));
        if ($data['bloque'] === 'efectivo_airpak') {
            F::check($op->tipo === 'egreso' && $op->id_entrada_salida && $op->entrada_salida->categoria === 'bancario'
                && $op->entrada_salida->medio === 'efectivo' && ! $op->id_cuenta_origen && ! $op->id_cuenta_destino,
                'id_operacion', 'AirPak requiere una salida bancaria del cajero entregada en efectivo, sin afectar banco/POS');
            F::check(! empty($data['receptor']) && (! empty($data['id_documento']) || ! empty($data['referencia'])), 'receptor', 'Identifique receptor y comprobante o referencia de entrega');
        } else {
            F::check($linked->isNotEmpty(), 'id_operacion', 'La partida debe afectar una cuenta seleccionada del diario');
        }
        if ($data['bloque'] === 'banco_cajeros') {
            F::check($op->tipo === 'transferencia' && $op->id_entrada_salida && $op->entrada_salida->categoria === 'bancario'
                && $linked->every(fn ($a) => $a->canal === 'bancentro'), 'id_operacion', 'Vincule el traslado bancario original del cajero con banco principal');
        } elseif ($data['bloque'] === 'remesa_pos') {
            $bank = $op->id_remesa ? LibroDiarioService::bank($op->remesa->banco) : null;
            F::check($bank && $op->tipo === 'transferencia' && $linked->count() === 1 && $linked->first()->canal === $bank,
                'id_operacion', 'Vincule la remesa original con el POS de su banco y moneda');
        } elseif (in_array($data['bloque'], ['cambio_tesoreria', 'cuenta_particular'], true)) {
            F::check(! empty($data['id_contraparte']), 'id_contraparte', 'Identifique el agente o titular de la cuenta');
            if ($data['bloque'] === 'cambio_tesoreria') {
                F::check(! empty($data['referencia']), 'referencia', 'Identifique la operacion de tesoreria y sus comprobantes');
            }
        }
        // Un origen bancario/remesa no puede ocultarse en otro bloque para eludir su control.
        if ($op->id_remesa) {
            F::check($data['bloque'] === 'remesa_pos', 'bloque', 'Las remesas se clasifican en remesa_pos');
        } elseif ($op->id_entrada_salida && $op->entrada_salida->categoria === 'bancario') {
            F::check(in_array($data['bloque'], ['banco_cajeros', 'efectivo_airpak'], true), 'bloque', 'Use el bloque de banco/cajero o efectivo AirPak');
        }
    }

    public function validateDetail(LibroDiario $root, array $data, ?Model $record = null): void
    {
        $this->references($data);
        $movement = Entradas_Y_Salidas::with('arqueo')->findOrFail($data['id_entrada_salida']);
        $this->source($root, $movement->arqueo);
        F::check($movement->estado !== 'anulado' && $movement->categoria === 'varios', 'id_entrada_salida', 'El detalle corresponde a un movimiento vigente de CxC/CxP (varios)');
        F::check(! empty($data['id_cuenta_auxiliar']) || ! empty($data['id_contraparte']), 'id_cuenta_auxiliar', 'Identifique cuenta auxiliar o contraparte');
        if (! empty($data['id_cuenta_auxiliar'])) {
            $account = CuentaFinanciera::findOrFail($data['id_cuenta_auxiliar']);
            F::active($account, 'id_cuenta_auxiliar');
            F::check(in_array($account->tipo, ['por_cobrar', 'por_pagar', 'fondo'], true)
                && F::date($account->fecha_apertura) <= F::date($root->fecha), 'id_cuenta_auxiliar', 'Seleccione una cuenta personal/CxC/CxP existente en esa fecha');
            F::check(empty($data['id_contraparte']) || ! $account->id_contraparte || $account->id_contraparte === (int) $data['id_contraparte'], 'id_contraparte', 'La cuenta pertenece a otra contraparte');
        }
        if (! empty($data['id_operacion'])) {
            $op = OperacionFinanciera::findOrFail($data['id_operacion']);
            F::active($op, 'id_operacion');
            F::check($op->id_entrada_salida === $movement->id && F::date($op->fecha) === F::date($root->fecha), 'id_operacion', 'La operacion debe reconocer este mismo movimiento original y fecha');
            F::check(! DetalleCuentaDiario::where('id_libro_diario', $root->id)->where('id_operacion', $op->id)->where('estado', 'registrado')
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists(), 'id_operacion', 'La operacion ya esta detallada');
        }
    }

    private function references(array $data): void
    {
        foreach (['id_documento' => 'DocumentoFinanciero', 'id_contraparte' => 'ContraparteFinanciera'] as $field => $type) {
            if (! empty($data[$field])) {
                $class = 'App\\Models\\Administracion\\'.$type;
                F::active($class::findOrFail($data[$field]), $field);
            }
        }
    }

    public function source(LibroDiario $root, \App\Models\Arqueo $day): void
    {
        F::check($day->estado === 'cerrado' && F::date($day->fecha) === F::date($root->fecha)
            && in_array($day->id_usuario, $root->usuarios_esperados, true), 'id_origen', 'El origen debe ser un arqueo cerrado de un cajero del mismo corte');
    }

    public function draft(Model $record): void
    {
        F::check($record->estado === 'borrador', 'estado', 'El corte o reporte ya no admite cambios');
        F::openDate($record->fecha);
    }

    public function confirmReport(Request $request, ReporteRemesaCentral $report): void
    {
        $this->draft($report);
        $lines = $report->detalles()->where('estado', 'registrado')->get();
        foreach ($report->agencias_esperadas as $agency) {
            foreach (['lafise', 'banpro'] as $bank) {
                foreach (['NIO', 'USD'] as $currency) {
                    foreach (['Entrada', 'Salida'] as $direction) {
                        F::check($lines->where('id_subagencia', $agency)->where('banco', $bank)->where('moneda', $currency)->where('tipo', $direction)->count() === 1,
                            'detalles', 'Declare las ocho combinaciones de banco, moneda y direccion por agencia, incluidos sus ceros');
                    }
                }
            }
        }
        $before = $report->getAttributes();
        $report->update(['estado' => 'confirmado', 'confirmado_at' => Carbon::now()]);
        F::audit($request, $report, 'confirmar', $before);
    }

    public function annul(Request $request, Model $record): void
    {
        F::check(! $record instanceof RevisionLibroDiario, 'registro', 'La revision es evidencia; registre otra revision');
        F::check(! in_array($record->estado, ['cerrado', 'confirmado', 'anulado'], true), 'estado', 'El registro ya no admite anulacion');
        if ($record instanceof LibroDiario || $record instanceof ReporteRemesaCentral) {
            $this->draft($record);
        } elseif ($record instanceof DetalleRemesaCentral) {
            $this->draft($record->reporte);
        } else {
            $this->draft($record->libro_diario);
        }
        if ($record instanceof ReporteRemesaCentral) {
            F::check(! LibroDiario::where('id_reporte_remesas', $record->id)->where('estado', '!=', 'anulado')->exists(), 'registro', 'Retire primero el reporte del diario en borrador');
        }
        if ($record instanceof LibroDiario) {
            foreach (['cuentas', 'partidas', 'detalles_cuenta'] as $relation) {
                foreach ($record->$relation()->where('estado', 'registrado')->get() as $child) {
                    $this->annul($request, $child);
                }
            }
        }
        $before = $record->getAttributes();
        $values = ['estado' => 'anulado'];
        foreach (['clave_activa', 'clave_cuenta_activa'] as $field) {
            if (in_array($field, $record->getFillable(), true)) {
                $values[$field] = null;
            }
        }
        $record->update($values);
        F::audit($request, $record, 'anular', $before);
    }
}

<?php

namespace App\Services\Administracion;

use App\Models\Administracion\ArqueoServicioExterno;
use App\Models\Administracion\ComisionAdministrativa;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\DocumentoFinanciero;
use App\Models\Administracion\ReporteRemesaCentral;
use App\Models\Administracion\TransaccionServicioExterno;
use App\Models\RemesaBancaria;
use App\Models\SubAgencia;
use App\Models\User;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\RecursosServiciosExternos as R;
use App\Support\Caja\Access;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ServicioExternoService
{
    public static function catalog(): array
    {
        return [
            'version' => '2026-11-06.v2', 'terminal' => 'principal', 'moneda_ganancia' => 'NIO',
            'alcance_tramo' => 'terminal_dia', 'metodo_tramo' => 'tarifa_unica_total',
            'servicios' => [
                'lafise' => ['transaccion_cuenta' => '7.00000000', 'remesa' => '20.00000000', 'sin_comision' => '0.00000000'],
                'banpro' => ['transaccion_cuenta' => null, 'recepcion_empresa' => '5.00000000',
                    'servicio_publico' => '4.00000000', 'remesa_local' => '12.00000000', 'remesa_extranjera' => null,
                    'sin_comision' => '0.00000000'],
            ],
            'tramos_transacciones_banpro' => [
                ['desde' => 1, 'hasta' => 10, 'tarifa_nio' => '5.00000000'],
                ['desde' => 11, 'hasta' => 20, 'tarifa_nio' => '5.50000000'],
                ['desde' => 21, 'hasta' => 30, 'tarifa_nio' => '6.00000000'],
                ['desde' => 31, 'hasta' => null, 'tarifa_nio' => '7.00000000'],
            ],
            'remesa_extranjera_usd' => '13.00000000',
            'tramos_remesa_extranjera_nio' => [
                ['desde_usd' => '0.01', 'hasta_usd' => '29.99', 'tarifa_nio' => '13.00000000'],
                ['desde_usd' => '30.00', 'hasta_usd' => '100.99', 'tarifa_nio' => '24.00000000'],
                ['desde_usd' => '101.00', 'hasta_usd' => '200.99', 'tarifa_nio' => '28.00000000'],
                ['desde_usd' => '201.00', 'hasta_usd' => '400.99', 'tarifa_nio' => '33.00000000'],
                ['desde_usd' => '401.00', 'hasta_usd' => '1500.00', 'tarifa_nio' => '38.00000000'],
            ],
        ];
    }

    public function save(Request $request, string $resource, array $data, ?Model $record = null): Model
    {
        unset($data['motivo']);
        $before = $record?->getAttributes();
        if ($record) {
            F::check($record->estado !== 'anulado', 'estado', 'El registro esta anulado');
            foreach (['fecha', 'id_arqueo_externo', 'clave'] as $field) {
                if (array_key_exists($field, $data)) {
                    $current = $field === 'fecha' ? F::date($record->$field) : $record->$field;
                    F::check((string) $data[$field] === (string) $current, $field, 'La identidad es inmutable; anule y registre la correccion');
                }
            }
        }
        $values = array_replace($record?->attributesToArray() ?? [], $data);
        $this->document($values['id_documento'] ?? null);
        if ($resource === 'arqueosserviciosexternos') {
            if ($record) {
                $this->draft($record);
            }
            F::openDate($values['fecha']);
            $values['agencias_esperadas'] = array_map('intval', $values['agencias_esperadas']);
            sort($values['agencias_esperadas']);
            foreach ($values['agencias_esperadas'] as $id) {
                $this->agency($id);
            }
            $values['id_responsable'] = $values['id_responsable'] ?? Access::user($request)->id;
            $responsible = User::findOrFail($values['id_responsable']);
            F::check(mb_strtolower(trim($responsible->estado_usuario)) === 'activo'
                && mb_strtolower(trim($responsible->rol_usuario)) === 'administrador',
                'id_responsable', 'La captura de esta terminal requiere un responsable administrador activo');
            F::check(! $record || ! $record->transacciones()->where('estado', 'registrado')
                ->whereNotIn('id_subagencia', $values['agencias_esperadas'])->exists(),
                'agencias_esperadas', 'No puede excluir una agencia que conserva transacciones');
            if (! $record) {
                $values['terminal'] = 'principal';
                $values['clave_activa'] = 'principal:'.$values['fecha'];
                $values['tarifario'] = self::catalog();
            }
        } else {
            $root = ArqueoServicioExterno::whereKey($values['id_arqueo_externo'])->lockForUpdate()->firstOrFail();
            $this->draft($root);
            if ($values['servicio'] === 'sin_comision') {
                $this->enableNoCommission($request, $root);
            }
            $this->validateTransaction($root, $values);
            $values['motivo_sin_comision'] = isset($values['motivo_sin_comision']) ? trim($values['motivo_sin_comision']) : null;
            $values['monto'] = F::amount($values['monto']);
            $values['monto_remesa_usd'] = isset($values['monto_remesa_usd']) ? F::amount($values['monto_remesa_usd'], 2) : null;
            $values['clave_remesa_activa'] = empty($values['id_remesa_caja']) ? null : 'remesa:'.$values['id_remesa_caja'];
        }
        $new = $record === null;
        $class = R::model($resource);
        $record ??= new $class;
        if ($new) {
            $values['id_usuario_registro'] = Access::user($request)->id;
        }
        $record->fill($values)->save();
        F::audit($request, $record, $new ? 'crear' : 'actualizar', $before);

        return $record;
    }

    public function validateTransaction(ArqueoServicioExterno $root, array $data, bool $closing = false): void
    {
        F::check(in_array((int) $data['id_subagencia'], $root->agencias_esperadas, true), 'id_subagencia', 'La agencia no pertenece al corte de la terminal');
        $this->agency((int) $data['id_subagencia']);
        $this->document($data['id_documento'] ?? null);
        if (! empty($data['id_cajero'])) {
            $cashier = User::findOrFail($data['id_cajero']);
            F::check(mb_strtolower(trim($cashier->estado_usuario)) === 'activo', 'id_cajero', 'El cajero de referencia no esta activo');
            F::check((int) Access::agency($cashier) === (int) $data['id_subagencia'], 'id_cajero', 'El cajero de referencia pertenece a otra agencia');
        }
        F::check(! empty($data['id_cajero']) || trim((string) ($data['nombre_cajero'] ?? '')) !== '',
            'nombre_cajero', 'Indique el cajero de referencia del registro, por ID o por nombre');
        $services = $root->tarifario['servicios'][$data['banco']];
        F::check($data['servicio'] === 'sin_clasificar' || array_key_exists($data['servicio'], $services), 'servicio', 'El servicio no corresponde al proveedor');
        if ($data['servicio'] === 'sin_comision') {
            F::check(trim((string) ($data['motivo_sin_comision'] ?? '')) !== '', 'motivo_sin_comision', 'Indique por que este movimiento no genera comision');
        } else {
            F::check(($data['motivo_sin_comision'] ?? null) === null, 'motivo_sin_comision', 'El motivo sin comision solo corresponde al servicio sin_comision; envie null al reclasificar');
        }
        F::check(! $closing || $data['servicio'] !== 'sin_clasificar', 'servicio', 'Clasifique el servicio antes de cerrar y calcular la ganancia definitiva');
        $needsBase = $data['banco'] === 'banpro' && $data['servicio'] === 'remesa_extranjera' && $data['moneda'] === 'NIO';
        if ($needsBase) {
            F::check(! $closing || ! empty($data['monto_remesa_usd']), 'monto_remesa_usd', 'Falta el importe original USD de la remesa pagada en cordobas');
            if (isset($data['monto_remesa_usd'])) {
                F::check(F::cmp($data['monto_remesa_usd'], '0.01') >= 0 && F::cmp($data['monto_remesa_usd'], '1500') <= 0,
                    'monto_remesa_usd', 'El tarifario informado cubre desde USD 0.01 hasta USD 1500.00');
            }
        } else {
            F::check(empty($data['monto_remesa_usd']), 'monto_remesa_usd', 'La base USD separada solo se utiliza para remesa extranjera BANPRO pagada en NIO');
        }
        if (! empty($data['id_remesa_caja'])) {
            $source = RemesaBancaria::with('arqueo')->findOrFail($data['id_remesa_caja']);
            $day = $source->arqueo;
            F::check($day && $day->estado === 'cerrado' && $source->estado !== 'anulado', 'id_remesa_caja', 'El enlace requiere una remesa vigente de una caja cerrada');
            F::check(F::date($day->fecha) === F::date($root->fecha) && $day->id_subagencia === (int) $data['id_subagencia']
                && LibroDiarioService::bank($source->banco) === $data['banco'] && $source->moneda === $data['moneda']
                && $source->tipo !== $data['tipo'] && F::cmp($source->monto, $data['monto']) === 0,
                'id_remesa_caja', 'El enlace exige fecha, agencia, banco, moneda e importe iguales y direccion opuesta al cajero');
            F::check(empty($data['id_cajero']) || (int) $data['id_cajero'] === $day->id_usuario,
                'id_cajero', 'El cajero de referencia no coincide con el origen de caja');
            F::check(trim((string) $source->numero_remesa) === trim((string) $data['numero_referencia']),
                'numero_referencia', 'La referencia debe coincidir con la remesa de caja enlazada');
        }
    }

    public function summary(ArqueoServicioExterno $root): array
    {
        if ($root->estado === 'cerrado') {
            return $root->resumen;
        }
        F::check($root->estado === 'borrador', 'estado', 'El arqueo externo esta anulado');
        $rows = $root->transacciones()->with('cajero')->where('estado', 'registrado')->orderBy('id')->get();
        // Un unico POS: no separar el tramo por cajero, agencia, moneda ni direccion.
        $bankCount = $rows->where('banco', 'banpro')->where('servicio', 'transaccion_cuenta')->count();
        $bankRate = $this->transactionRate($root->tarifario, $bankCount);
        $result = ['id_arqueo_externo' => $root->id, 'fecha' => F::date($root->fecha), 'terminal' => $root->terminal,
            'id_responsable' => $root->id_responsable, 'agencias_esperadas' => $root->agencias_esperadas,
            'tarifario' => $root->tarifario, 'transacciones_cuenta_banpro' => $bankCount,
            'tarifa_transacciones_banpro_nio' => $bankRate, 'totales' => $this->bucket(),
            'por_banco' => ['lafise' => $this->bucket(), 'banpro' => $this->bucket()],
            'por_agencia_banco' => [], 'por_servicio' => [], 'lineas_reporte' => [], 'transacciones' => [], 'pendientes' => []];
        try {
            $this->validateHeader($root);
        } catch (ValidationException $e) {
            $result['pendientes'][] = ['id_transaccion' => null, 'errores' => $e->errors()];
        }
        $agencies = [];
        foreach ($root->agencias_esperadas as $agency) {
            foreach (['lafise', 'banpro'] as $bank) {
                $agencies[$agency.':'.$bank] = ['id_subagencia' => $agency, 'banco' => $bank, ...$this->bucket()];
            }
        }
        foreach ($rows as $row) {
            $data = $row->attributesToArray();
            $data['nombre_cajero'] ??= $row->cajero?->usuario;
            $errors = [];
            try {
                $this->validateTransaction($root, $data, true);
            } catch (ValidationException $e) {
                $errors = $e->errors();
                $result['pendientes'][] = ['id_transaccion' => $row->id, 'errores' => $errors];
            }
            $fee = $errors === [] ? $this->fee($root->tarifario, $data, $bankRate) : null;
            $data['tarifa_nio'] = $fee;
            $data['ganancia_nio'] = $fee; // Cada registro es una transaccion; el principal no es la ganancia.
            $data['calculo'] = ['version' => $root->tarifario['version'], 'servicio' => $data['servicio'],
                'moneda_ganancia' => 'NIO', 'cantidad' => 1, 'tarifa_nio' => $fee,
                'base_usd' => $data['servicio'] === 'remesa_extranjera' && $data['moneda'] === 'USD' ? $data['monto'] : $data['monto_remesa_usd'],
                'motivo_sin_comision' => $data['motivo_sin_comision'] ?? null,
                'cantidad_terminal_dia' => $data['banco'] === 'banpro' && $data['servicio'] === 'transaccion_cuenta' ? $bankCount : null];
            $result['transacciones'][] = $data;
            $key = $data['id_subagencia'].':'.$data['banco'];
            $serviceKey = $data['banco'].':'.$data['servicio'];
            $result['por_servicio'][$serviceKey] ??= ['banco' => $data['banco'], 'servicio' => $data['servicio'], ...$this->bucket()];
            $this->accumulate($result['totales'], $data, $fee);
            $this->accumulate($result['por_banco'][$data['banco']], $data, $fee);
            $this->accumulate($result['por_servicio'][$serviceKey], $data, $fee);
            $this->accumulate($agencies[$key], $data, $fee);
        }
        foreach ($agencies as $bucket) {
            $result['por_agencia_banco'][] = $bucket;
            foreach (['NIO', 'USD'] as $currency) {
                foreach (['Entrada' => 'entradas', 'Salida' => 'salidas'] as $type => $field) {
                    $result['lineas_reporte'][] = ['id_subagencia' => $bucket['id_subagencia'], 'banco' => $bucket['banco'],
                        'moneda' => $currency, 'tipo' => $type, 'monto' => $bucket[$field][$currency],
                        'cantidad' => $bucket['cantidades'][$currency][$type]];
                }
            }
        }
        $result['por_servicio'] = array_values($result['por_servicio']);
        $result['completo'] = $result['pendientes'] === [];
        $result['huella'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $result;
    }

    public function close(Request $request, ArqueoServicioExterno $root, string $hash): array
    {
        $this->draft($root);
        $result = $this->summary($root);
        F::check(hash_equals($result['huella'], $hash), 'huella', 'El registro cambio; consulte el resumen y revise sus datos antes de cerrar');
        F::check($result['completo'], 'transacciones', 'Hay servicios sin clasificar, importes USD o referencias pendientes');
        $this->validateHeader($root);
        foreach ($result['transacciones'] as $data) {
            $row = TransaccionServicioExterno::findOrFail($data['id']);
            $before = $row->getAttributes();
            $row->update(['tarifa_nio' => $data['tarifa_nio'], 'ganancia_nio' => $data['ganancia_nio'], 'calculo' => $data['calculo']]);
            F::audit($request, $row, 'calcular_ganancia', $before);
        }
        $before = $root->getAttributes();
        $root->update(['estado' => 'cerrado', 'resumen' => $result, 'cerrado_at' => Carbon::now()]);
        F::audit($request, $root, 'cerrar', $before);

        return $result;
    }

    public function annul(Request $request, Model $record): void
    {
        $root = $record instanceof ArqueoServicioExterno ? $record : $record->arqueo_externo;
        $this->draft($root);
        F::check($record->estado !== 'anulado', 'estado', 'El registro ya fue anulado');
        if ($record instanceof ArqueoServicioExterno) {
            foreach ($record->transacciones()->where('estado', 'registrado')->get() as $row) {
                $this->annul($request, $row);
            }
        }
        $before = $record->getAttributes();
        $record->update($record instanceof ArqueoServicioExterno
            ? ['estado' => 'anulado', 'clave_activa' => null] : ['estado' => 'anulado', 'clave_remesa_activa' => null]);
        F::audit($request, $record, 'anular', $before);
    }

    public function generateReport(Request $request, ArqueoServicioExterno $root, string $cutoff): ReporteRemesaCentral
    {
        F::check($root->estado === 'cerrado', 'estado', 'Cierre el registro de la terminal antes de generar su reporte');
        F::check(F::date($cutoff) === F::date($root->fecha), 'corte_at', 'El corte debe corresponder al dia registrado');
        $existing = $root->reporte_remesas()->first();
        if ($existing) {
            F::check($existing->estado === 'confirmado' && $existing->corte_at->equalTo(Carbon::parse($cutoff)),
                'corte_at', 'El reporte de esta terminal ya fue emitido con otro corte');

            return $existing;
        }
        foreach ($root->resumen['lineas_reporte'] as $line) {
            F::check(F::cmp($line['monto'], '9999999999999999.99999999') <= 0,
                'reporte', 'Un total por agencia, banco, moneda y direccion supera la precision del reporte central (16 enteros y ocho decimales)');
        }
        $service = new RegistroLibroDiarioService;
        $report = $service->save($request, 'reportesremesascentrales', ['fecha' => F::date($root->fecha),
            'corte_at' => $cutoff, 'referencia' => 'TERMINAL-'.$root->id, 'fuente' => 'Registro administrativo de servicios externos: terminal principal',
            'agencias_esperadas' => $root->agencias_esperadas, 'id_documento' => $root->id_documento]);
        $before = $report->getAttributes();
        $report->update(['id_arqueo_externo' => $root->id]);
        F::audit($request, $report, 'vincular_terminal', $before);
        foreach ($root->resumen['lineas_reporte'] as $line) {
            unset($line['cantidad']);
            $service->save($request, 'detallesremesascentrales', ['id_reporte' => $report->id, ...$line]);
        }
        $service->confirmReport($request, $report->refresh());

        return $report->fresh();
    }

    public function commissions(Request $request, ArqueoServicioExterno $root, array $input): array
    {
        F::check($root->estado === 'cerrado', 'estado', 'Cierre el registro de la terminal antes de reconocer las comisiones');
        F::check($input['fecha'] >= F::date($root->fecha), 'fecha', 'El reconocimiento no puede preceder al servicio');
        $requested = [];
        $plans = [];
        foreach ($input['cuentas'] as $mapping) {
            F::unknown($mapping, ['banco', 'id_cuenta']);
            F::check(! isset($requested[$mapping['banco']]), 'cuentas', 'Cada proveedor se declara una sola vez');
            $requested[$mapping['banco']] = true;
            $amount = $root->resumen['por_banco'][$mapping['banco']]['ganancia_nio'];
            F::check(F::cmp($amount, 0) > 0, 'cuentas', 'El proveedor seleccionado no tiene ganancias para reconocer');
            $existing = $root->comisiones()->where('banco_externo', $mapping['banco'])->where('estado', 'registrado')->first();
            if ($existing) {
                F::check($existing->estado === 'registrado' && $existing->id_cuenta === (int) $mapping['id_cuenta']
                    && F::date($existing->fecha) === $input['fecha'], 'cuentas', 'La comision vigente ya fue reconocida con otra fecha o cuenta');
                $plans[] = ['existing' => $existing];

                continue;
            }
            F::openDate($input['fecha']);
            $account = CuentaFinanciera::findOrFail($mapping['id_cuenta']);
            F::active($account, 'id_cuenta');
            F::check($account->tipo === 'por_cobrar' && $account->moneda === 'NIO' && $account->rubro_capital === 'pendiente' && $account->id_contraparte,
                'id_cuenta', 'Use una cuenta por cobrar NIO incluida en pendientes del capital e identificada con el proveedor');
            F::active($account->contraparte, 'id_cuenta');
            F::check($account->id_subagencia === null, 'id_cuenta', 'La comision total de la terminal requiere una cuenta central, sin agencia individual');
            F::check(! ComisionAdministrativa::where('id_cuenta', $account->id)->whereNotNull('id_arqueo_externo')
                ->where('estado', 'registrado')->where('banco_externo', '!=', $mapping['banco'])->exists(),
                'id_cuenta', 'La cuenta ya reconoce comisiones del otro proveedor; utilice una cuenta independiente por banco');
            $plans[] = ['banco' => $mapping['banco'], 'account' => $account, 'amount' => $amount];
        }
        $result = [];
        foreach ($plans as $plan) {
            if (isset($plan['existing'])) {
                $result[] = $plan['existing'];

                continue;
            }
            $commission = (new AdministracionService)->save($request, 'comisiones', [
                'fecha' => $input['fecha'], 'periodo_desde' => F::date($root->fecha), 'periodo_hasta' => F::date($root->fecha),
                'tipo' => 'proveedor', 'naturaleza' => 'cobrar', 'id_contraparte' => $plan['account']->id_contraparte,
                'id_cuenta' => $plan['account']->id, 'moneda' => 'NIO', 'monto' => $plan['amount'],
                'id_documento' => $root->id_documento,
                'observacion' => 'Ganancias '.$plan['banco'].' de terminal principal, arqueo externo '.$root->id]);
            $before = $commission->getAttributes();
            $commission->update(['id_arqueo_externo' => $root->id, 'banco_externo' => $plan['banco'],
                'clave_externa_activa' => $root->id.':'.$plan['banco']]);
            F::audit($request, $commission, 'vincular_terminal', $before);
            $result[] = $commission->fresh();
        }

        return $result;
    }

    public static function financialCoverage(string $date): array
    {
        $result = ['fuentes' => [], 'pendientes' => []];
        $roots = ArqueoServicioExterno::with('comisiones.cuenta')->whereDate('fecha', '<=', $date)
            ->where('estado', '!=', 'anulado')->orderBy('fecha')->orderBy('id')->get();
        $banksByAccount = [];
        foreach ($roots as $root) {
            foreach ($root->comisiones as $commission) {
                if ($commission->estado === 'registrado' && F::date($commission->fecha) <= $date) {
                    $banksByAccount[$commission->id_cuenta][$commission->banco_externo ?? ''] = true;
                }
            }
        }
        foreach ($roots as $root) {
            $source = ['id_arqueo_externo' => $root->id, 'fecha' => F::date($root->fecha), 'estado' => $root->estado,
                'huella' => $root->resumen['huella'] ?? null, 'comisiones' => []];
            if ($root->estado !== 'cerrado') {
                $result['pendientes'][] = ['tipo' => 'servicios_externos_sin_cerrar', 'id_arqueo_externo' => $root->id];
            } else {
                foreach (['lafise', 'banpro'] as $bank) {
                    $amount = $root->resumen['por_banco'][$bank]['ganancia_nio'];
                    if (F::cmp($amount, 0) <= 0) {
                        continue;
                    }
                    $commission = $root->comisiones->first(fn ($c) => $c->banco_externo === $bank && $c->estado === 'registrado'
                        && F::date($c->fecha) <= $date);
                    if (! $commission) {
                        $result['pendientes'][] = ['tipo' => 'comisiones_externas_sin_reconocer',
                            'id_arqueo_externo' => $root->id, 'banco' => $bank, 'ganancia_nio' => $amount];
                    } else {
                        $source['comisiones'][] = ['banco' => $bank, 'id_comision' => $commission->id, 'monto' => $commission->monto];
                        $account = $commission->cuenta;
                        if (! $account || $account->tipo !== 'por_cobrar' || $account->moneda !== 'NIO'
                            || $account->rubro_capital !== 'pendiente' || $account->id_subagencia !== null
                            || $commission->moneda !== 'NIO' || $commission->tipo !== 'proveedor' || $commission->naturaleza !== 'cobrar'
                            || count($banksByAccount[$commission->id_cuenta] ?? []) > 1
                            || F::cmp($commission->monto, $amount) !== 0) {
                            $result['pendientes'][] = ['tipo' => 'comision_externa_inconsistente', 'id_arqueo_externo' => $root->id,
                                'banco' => $bank, 'id_comision' => $commission->id, 'id_cuenta' => $commission->id_cuenta];
                        }
                    }
                }
            }
            $result['fuentes'][] = $source;
        }

        return $result;
    }

    private function transactionRate(array $tariff, int $count): string
    {
        foreach ($tariff['tramos_transacciones_banpro'] as $band) {
            if ($count >= $band['desde'] && ($band['hasta'] === null || $count <= $band['hasta'])) {
                return $band['tarifa_nio'];
            }
        }

        return F::amount();
    }

    private function fee(array $tariff, array $data, string $bankRate): string
    {
        if ($data['banco'] === 'banpro' && $data['servicio'] === 'transaccion_cuenta') {
            return $bankRate;
        }
        if ($data['banco'] === 'banpro' && $data['servicio'] === 'remesa_extranjera') {
            if ($data['moneda'] === 'USD') {
                return $tariff['remesa_extranjera_usd'];
            }
            foreach ($tariff['tramos_remesa_extranjera_nio'] as $band) {
                if (F::cmp($data['monto_remesa_usd'], $band['desde_usd']) >= 0 && F::cmp($data['monto_remesa_usd'], $band['hasta_usd']) <= 0) {
                    return $band['tarifa_nio'];
                }
            }
        }

        return $tariff['servicios'][$data['banco']][$data['servicio']];
    }

    private function bucket(): array
    {
        return ['cantidad' => 0, 'cantidad_con_comision' => 0, 'cantidad_sin_comision' => 0,
            'entradas' => ['NIO' => F::amount(), 'USD' => F::amount()],
            'salidas' => ['NIO' => F::amount(), 'USD' => F::amount()], 'neto' => ['NIO' => F::amount(), 'USD' => F::amount()],
            'cantidades' => ['NIO' => ['Entrada' => 0, 'Salida' => 0], 'USD' => ['Entrada' => 0, 'Salida' => 0]],
            'ganancia_nio' => F::amount(), 'ganancia_calculada_nio' => F::amount(), 'pendientes' => 0];
    }

    private function accumulate(array &$bucket, array $data, ?string $fee): void
    {
        $currency = $data['moneda'];
        $bucket['cantidad']++;
        $bucket['cantidades'][$currency][$data['tipo']]++;
        $key = $data['tipo'] === 'Entrada' ? 'entradas' : 'salidas';
        $bucket[$key][$currency] = F::add($bucket[$key][$currency], $data['monto']);
        $bucket['neto'][$currency] = F::sub($bucket['entradas'][$currency], $bucket['salidas'][$currency]);
        if ($fee === null) {
            $bucket['pendientes']++;
            $bucket['ganancia_nio'] = null;
        } else {
            if ($data['servicio'] === 'sin_comision') {
                $bucket['cantidad_sin_comision']++;
            } else {
                $bucket['cantidad_con_comision']++;
            }
            $bucket['ganancia_calculada_nio'] = F::add($bucket['ganancia_calculada_nio'], $fee);
            if ($bucket['ganancia_nio'] !== null) {
                $bucket['ganancia_nio'] = $bucket['ganancia_calculada_nio'];
            }
        }
    }

    private function enableNoCommission(Request $request, ArqueoServicioExterno $root): void
    {
        if (($root->tarifario['version'] ?? null) !== '2026-11-05.v1') {
            return;
        }
        // La nueva clase no altera las tarifas anteriores. Solo se habilita al capturar un borrador.
        $before = $root->getAttributes();
        $tariff = $root->tarifario;
        foreach (['lafise', 'banpro'] as $bank) {
            $tariff['servicios'][$bank]['sin_comision'] = F::amount();
        }
        $tariff['version'] = '2026-11-06.v2';
        $root->update(['tarifario' => $tariff]);
        F::audit($request, $root, 'habilitar_sin_comision', $before);
    }

    private function document(?int $id): void
    {
        if ($id) {
            F::active(DocumentoFinanciero::findOrFail($id), 'id_documento');
        }
    }

    private function validateHeader(ArqueoServicioExterno $root): void
    {
        $responsible = User::findOrFail($root->id_responsable);
        F::check(mb_strtolower(trim($responsible->estado_usuario)) === 'activo'
            && mb_strtolower(trim($responsible->rol_usuario)) === 'administrador', 'id_responsable', 'El responsable debe ser administrador activo');
        foreach ($root->agencias_esperadas as $id) {
            $this->agency($id);
        }
        $this->document($root->id_documento);
    }

    private function agency(int $id): void
    {
        $agency = SubAgencia::findOrFail($id);
        F::check(mb_strtolower(trim($agency->estado_agencia)) === 'activo', 'id_subagencia', 'La agencia de referencia no esta activa');
    }

    private function draft(ArqueoServicioExterno $root): void
    {
        F::check($root->estado === 'borrador', 'estado', 'El registro de la terminal ya no admite cambios de captura');
        F::openDate($root->fecha);
    }
}

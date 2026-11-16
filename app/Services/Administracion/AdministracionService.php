<?php

namespace App\Services\Administracion;

use App\Models\Administracion\ActivoAdministrativo;
use App\Models\Administracion\AplicacionAnticipoObra;
use App\Models\Administracion\AplicacionCuenta;
use App\Models\Administracion\AplicacionFinanciera;
use App\Models\Administracion\ArqueoMadre;
use App\Models\Administracion\CentroCosto;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\DepositoLiquidacion;
use App\Models\Administracion\DetalleArqueoMadre;
use App\Models\Administracion\DetalleReporteMatriz;
use App\Models\Administracion\EventoActivo;
use App\Models\Administracion\Financiamiento;
use App\Models\Administracion\GastoAdministrativo;
use App\Models\Administracion\GestionIncidencia;
use App\Models\Administracion\IncidenciaFinanciera;
use App\Models\Administracion\MovimientoCuenta;
use App\Models\Administracion\MovimientoObra;
use App\Models\Administracion\OperacionFinanciera;
use App\Models\Administracion\ReporteMatriz;
use App\Models\Arqueo;
use App\Models\EnvioDhl;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\Recursos;
use App\Support\Caja\Access;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class AdministracionService
{
    private const METADATA = ['nombre', 'concepto', 'observacion', 'id_documento', 'ubicacion', 'ticket', 'caso', 'biller', 'referencia_cliente', 'motivo_incidencia'];

    public function save(Request $request, string $resource, array $input, ?Model $record = null): Model
    {
        $def = Recursos::DEFINICIONES[$resource];
        F::check($resource !== 'historial', 'recurso', 'El historial es de solo lectura');
        $before = $record?->getAttributes();
        if ($record) {
            F::check(! in_array($record->estado, ['cerrado', 'confirmado', 'anulado', 'revisado', 'conciliado', 'con_diferencias'], true),
                'estado', 'El registro conserva un cierre, revision o anulacion; no admite esta edicion');
            foreach (array_keys($input) as $field) {
                $diaryLink = $resource === 'arqueosmadre' && $field === 'id_libro_diario';
                if (! $diaryLink && ((str_starts_with($field, 'id_') && $field !== 'id_documento') || in_array($field, ['codigo', 'clave', 'origen_tipo', 'id_origen'], true))) {
                    F::check((string) ($input[$field] ?? '') === (string) ($record->$field ?? ''), $field, 'La identidad o relacion de origen es inmutable');
                }
            }
            $metadata = $resource === 'obligacioneslaborales' ? array_diff(self::METADATA, ['concepto']) : self::METADATA;
            $financial = array_diff(array_keys($input), [...$metadata, 'motivo', ...($def['catalog'] ? ['estado'] : [])]);
            if ($financial !== []) {
                if ($record instanceof MovimientoCuenta) {
                    (new LedgerService)->assertEntryMutation($record);
                }
                if ($record->fecha || $record->fecha_apertura) {
                    F::openDate($record->fecha ?? $record->fecha_apertura);
                }
                F::check(! $record->origen_automatico && ! $record->id_movimiento_origen && $resource !== 'operaciones'
                    && $resource !== 'aplicaciones' && $resource !== 'aplicacionescuenta' && $resource !== 'aplicacionesobra'
                    && $resource !== 'gestionesincidencia' && $resource !== 'eventosactivos' && $resource !== 'cierres' && $resource !== 'movimientospersonales',
                    'registro', 'La operacion contabilizada se corrige mediante anulacion o ajuste, conservando su origen');
                F::check(! in_array($resource, ['depositosliquidacion', 'compensacioneslaborales'], true), 'registro', 'Use las acciones del registro o anule antes de corregir');
                if (! in_array($resource, ['arqueosmadre', 'liquidaciones'], true)) {
                    F::check(! $this->dependencies($record), 'registro', 'Hay registros dependientes; no puede cambiar los datos financieros');
                }
            } else {
                if ($resource === 'detallesreporte') {
                    F::check($record->reporte->estado === 'borrador', 'id_reporte', 'El reporte ya fue confirmado o anulado');
                }
                F::check(! $record->origen_automatico, 'registro', 'La clasificacion automatica se administra desde su origen');
                if (! empty($input['id_documento'])) {
                    F::active(\App\Models\Administracion\DocumentoFinanciero::findOrFail($input['id_documento']), 'id_documento');
                }
                $record->fill($input)->save();
                F::audit($request, $record, 'actualizar', $before);

                return $record;
            }
        }
        $data = array_replace($def['defaults'], $record?->attributesToArray() ?? [], $input);
        if ($resource !== 'cierres') {
            unset($data['motivo']);
        }
        if (isset($data['periodo_desde'], $data['periodo_hasta'])) {
            F::check($data['periodo_hasta'] >= $data['periodo_desde'], 'periodo_hasta', 'El periodo esta invertido');
        }
        $data = $this->prepare($request, $resource, $data, $record);
        $date = $data['fecha'] ?? $data['fecha_apertura'] ?? null;
        if ($date !== null) {
            F::openDate($date);
        }
        if ($resource === 'aplicacionescuenta') {
            return (new LedgerService)->apply($request, MovimientoCuenta::findOrFail($data['id_cargo']),
                MovimientoCuenta::findOrFail($data['id_abono']), $data['fecha'], $data['monto']);
        }
        $new = $record === null;
        $model = F::model($resource);
        $record ??= new $model;
        if ($new) {
            $data['id_usuario_registro'] = Access::user($request)->id;
        }
        $record->fill($data)->save();
        if ($new) {
            $this->post($request, $resource, $record);
        }
        F::audit($request, $record, $new ? 'crear' : 'actualizar', $before);

        return $record;
    }

    private function prepare(Request $request, string $resource, array $data, ?Model $record): array
    {
        foreach (['id_contraparte' => 'contrapartes', 'id_centro_costo' => 'centroscosto', 'id_proyecto' => 'proyectos', 'id_documento' => 'documentos'] as $field => $parentResource) {
            if (! empty($data[$field])) {
                $parent = (F::model($parentResource))::findOrFail($data[$field]);
                F::active($parent, $field);
            }
        }
        if ($resource === 'arqueosmadre') {
            return (new ConsolidacionService)->prepare($data, $record);
        }
        if ($resource === 'documentos') {
            F::check(preg_match('/^[a-fA-F0-9]{64}$/D', $data['sha256']) === 1, 'sha256', 'Indique el SHA-256 hexadecimal del documento');
            $data['sha256'] = strtolower($data['sha256']);
        } elseif ($resource === 'cuentas') {
            $allowed = match ($data['tipo']) {
                'banco', 'pos' => ['banco', 'excluido'], 'liquidacion' => ['excluido'],
                'por_pagar' => ['pendiente', 'deuda', 'excluido'], default => ['pendiente', 'excluido'],
            };
            F::check(in_array($data['rubro_capital'], $allowed, true), 'rubro_capital', 'El rubro no corresponde al tipo de cuenta');
        } elseif ($resource === 'operaciones') {
            $data = (new LedgerService)->prepareOperation($data);
        } elseif ($resource === 'movimientospersonales') {
            $data = (new CuentaPersonalService)->prepare($data);
        } elseif ($resource === 'movimientoscuenta') {
            $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
            F::active($account, 'id_cuenta');
            (new LedgerService)->assertPostingDate($account, $data['fecha']);
            F::check(! in_array($account->tipo, ['banco', 'pos'], true), 'id_cuenta', 'Los bancos/POS se mueven mediante operaciones financieras');
            F::check(empty($data['id_operacion']) && $request->filled('motivo'), 'motivo', 'Un ajuste auxiliar requiere motivo; los pagos reales usan aplicaciones financieras');
            F::check($data['fecha'] >= $account->fecha_apertura->toDateString(), 'fecha', 'La cuenta no existia en esa fecha');
        } elseif ($resource === 'aplicacionescuenta') {
            F::check(! MovimientoCuenta::findOrFail($data['id_abono'])->origen_automatico,
                'id_abono', 'Los abonos automaticos se administran desde su documento de origen; una reduccion sin dinero utiliza un abono manual');
        } elseif ($resource === 'reportesmatriz') {
            F::check(Carbon::parse($data['corte_at'])->toDateString() === $data['fecha'], 'corte_at', 'El corte debe corresponder a la fecha del reporte');
        } elseif ($resource === 'detallesreporte') {
            F::check(ReporteMatriz::findOrFail($data['id_reporte'])->estado === 'borrador', 'id_reporte', 'El reporte ya fue confirmado o anulado');
            F::check(! DetalleReporteMatriz::where('id_reporte', $data['id_reporte'])->where('servicio', $data['servicio'])
                ->where('moneda', $data['moneda'])->where('estado', 'registrado')
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists(), 'servicio', 'El reporte ya tiene esa linea vigente');
        } elseif ($resource === 'conciliaciones') {
            $data = (new MatrizService)->reconcile($data);
        } elseif ($resource === 'liquidaciones') {
            $data = (new MatrizService)->prepareSettlement($data, $record);
        } elseif ($resource === 'depositosliquidacion') {
            $root = \App\Models\Administracion\LiquidacionMatriz::findOrFail($data['id_liquidacion']);
            $operation = OperacionFinanciera::findOrFail($data['id_operacion']);
            F::active($operation, 'id_operacion');
            F::check($root->estado === 'borrador' && $operation->tipo === 'egreso', 'id_operacion', 'Declare un egreso real en una liquidacion abierta');
            F::check($data['fecha'] >= $operation->fecha->toDateString() && $data['fecha'] <= $root->fecha->toDateString(), 'fecha', 'La declaracion debe estar entre la operacion y el corte');
            F::check(! AplicacionFinanciera::where('id_operacion', $operation->id)->where('estado', 'registrado')->exists(), 'id_operacion', 'El egreso ya tiene otra aplicacion');
            F::check(! \App\Models\Administracion\MovimientoPersonal::where('id_operacion', $operation->id)->where('estado', 'registrado')->exists(),
                'id_operacion', 'El dinero ya tiene un movimiento personal');
            F::check(! \App\Models\Administracion\ObligacionLaboral::where('id_operacion_anticipo', $operation->id)->where('estado', 'registrado')->exists(),
                'id_operacion', 'El egreso ya representa un anticipo laboral');
            F::check(! DepositoLiquidacion::where('id_operacion', $operation->id)->where('estado', '!=', 'anulado')->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists(),
                'id_operacion', 'El egreso ya fue declarado como deposito');
            $data['clave_operacion_activa'] = 'operacion:'.$operation->id;
            if (empty($data['fecha_acreditacion'])) {
                F::check(! empty($data['id_cuenta_transito']), 'id_cuenta_transito', 'Un deposito pendiente requiere una cuenta por cobrar de fondos en transito');
            }
            if (! empty($data['id_cuenta_transito'])) {
                $transit = CuentaFinanciera::findOrFail($data['id_cuenta_transito']);
                F::active($transit, 'id_cuenta_transito');
                F::check($transit->tipo === 'por_cobrar' && $transit->rubro_capital === 'pendiente'
                    && $transit->moneda === $operation->moneda, 'id_cuenta_transito', 'Use una cuenta por cobrar de la moneda original, incluida en pendientes');
            }
            if (! empty($data['fecha_acreditacion'])) {
                F::check($data['fecha_acreditacion'] >= $data['fecha'] && $data['fecha_acreditacion'] <= $root->fecha->toDateString(), 'fecha_acreditacion', 'La acreditacion declarada debe estar dentro del corte');
                $data['estado'] = 'acreditado';
            }
        } elseif ($resource === 'gastos') {
            if (! empty($data['id_cuenta'])) {
                $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
                F::active($account, 'id_cuenta');
                F::check($account->tipo === 'por_pagar' && $account->moneda === $data['moneda'], 'id_cuenta', 'El gasto requiere una cuenta por pagar de su moneda');
                F::check($data['fecha'] >= $account->fecha_apertura->toDateString(), 'fecha', 'La cuenta no existia en esa fecha');
            }
            if (! empty($data['id_centro_costo'])) {
                $center = CentroCosto::findOrFail($data['id_centro_costo']);
                F::check(empty($data['id_subagencia']) || ! $center->id_subagencia || (int) $data['id_subagencia'] === $center->id_subagencia,
                    'id_subagencia', 'El centro de costo pertenece a otra agencia');
                $data['id_subagencia'] ??= $center->id_subagencia;
            }
        } elseif (in_array($resource, ['obligacioneslaborales', 'comisiones'], true)) {
            F::check($resource !== 'obligacioneslaborales' || $data['concepto'] !== 'descuento', 'concepto', 'Los descuentos de anticipos se registran como compensaciones laborales');
            $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
            F::active($account, 'id_cuenta');
            $receivable = $resource === 'comisiones' ? $data['naturaleza'] === 'cobrar' : $data['concepto'] === 'anticipo';
            F::check($account->moneda === $data['moneda'] && $account->tipo === ($receivable ? 'por_cobrar' : 'por_pagar'),
                'id_cuenta', 'Seleccione la cuenta auxiliar de la naturaleza y moneda correspondientes');
            F::check($data['fecha'] >= $account->fecha_apertura->toDateString(), 'fecha', 'La cuenta no existia en esa fecha');
            if ($resource === 'obligacioneslaborales' && ! empty($data['id_operacion_anticipo'])) {
                $operation = OperacionFinanciera::findOrFail($data['id_operacion_anticipo']);
                F::active($operation, 'id_operacion_anticipo');
                F::check($data['concepto'] === 'anticipo' && $operation->tipo === 'egreso' && $operation->moneda === $data['moneda']
                    && F::cmp($operation->monto, $data['monto']) === 0 && $operation->fecha->toDateString() <= $data['fecha'],
                    'id_operacion_anticipo', 'Vincule el egreso original por el importe completo del anticipo y en su moneda');
                F::check(! AplicacionFinanciera::where('id_operacion', $operation->id)->where('estado', 'registrado')->exists()
                    && ! \App\Models\Administracion\MovimientoPersonal::where('id_operacion', $operation->id)->where('estado', 'registrado')->exists()
                    && ! DepositoLiquidacion::where('id_operacion', $operation->id)->where('estado', '!=', 'anulado')->exists()
                    && ! \App\Models\Administracion\ObligacionLaboral::where('id_operacion_anticipo', $operation->id)->where('estado', 'registrado')->exists(),
                    'id_operacion_anticipo', 'El egreso ya tiene una clasificacion financiera');
            }
        } elseif ($resource === 'movimientosobra') {
            if (isset($data['cantidad']) || isset($data['precio_unitario'])) {
                F::check(isset($data['cantidad'], $data['precio_unitario']), 'cantidad', 'Informe cantidad y precio juntos');
                $product = F::mul($data['cantidad'], $data['precio_unitario']);
                $data['monto'] ??= $product;
            }
            F::check(isset($data['monto']) && F::cmp($data['monto'], 0) > 0, 'monto', 'Indique el importe positivo del registro de obra');
        } elseif ($resource === 'aplicacionesobra') {
            $advance = MovimientoObra::findOrFail($data['id_anticipo']);
            $cost = MovimientoObra::findOrFail($data['id_costo']);
            F::active($advance, 'id_anticipo');
            F::active($cost, 'id_costo');
            F::check($advance->tipo === 'anticipo' && $cost->tipo === 'costo' && $advance->id_proyecto === $cost->id_proyecto
                && $advance->moneda === $cost->moneda, 'id_costo', 'El anticipo y costo deben ser de la misma obra y moneda');
            F::check($data['fecha'] >= $advance->fecha->toDateString() && $data['fecha'] >= $cost->fecha->toDateString(), 'fecha', 'La aplicacion no puede preceder a sus documentos');
            $used = F::sum(AplicacionAnticipoObra::where('id_anticipo', $advance->id)->where('estado', 'registrado')->whereDate('fecha', '<=', $data['fecha'])->get(), 'monto');
            $paid = F::sum(AplicacionFinanciera::where('id_obra', $advance->id)->where('estado', 'registrado')->whereDate('fecha', '<=', $data['fecha'])->get(), 'monto_destino');
            F::check(F::cmp($data['monto'], F::sub($paid, $used)) <= 0, 'monto', 'El anticipo no tiene ese importe pagado sin aplicar');
            F::check(F::cmp($data['monto'], (new AplicacionFinancieraService)->pending('id_obra', $cost)) <= 0, 'monto', 'La aplicacion excede el costo pendiente');
        } elseif ($resource === 'compensacioneslaborales') {
            $withholding = ! empty($data['id_retencion']);
            F::check($withholding !== ! empty($data['id_anticipo']), 'id_anticipo', 'Indique un anticipo o una retencion, nunca ambos');
            $advance = \App\Models\Administracion\ObligacionLaboral::findOrFail($data[$withholding ? 'id_retencion' : 'id_anticipo']);
            $obligation = \App\Models\Administracion\ObligacionLaboral::findOrFail($data['id_obligacion']);
            F::active($advance, 'id_anticipo');
            F::active($obligation, 'id_obligacion');
            F::check($advance->concepto === ($withholding ? 'inss_laboral' : 'anticipo')
                && ($withholding ? $obligation->concepto === 'salario' : ! in_array($obligation->concepto, ['anticipo', 'descuento'], true))
                && $advance->id_trabajador === $obligation->id_trabajador && $advance->moneda === $obligation->moneda,
                'id_obligacion', 'Compense documentos compatibles del mismo trabajador y moneda');
            F::check($data['fecha'] >= $advance->fecha->toDateString() && $data['fecha'] >= $obligation->fecha->toDateString(), 'fecha', 'La compensacion precede a sus origenes');
            foreach ($withholding ? [$obligation] : [$advance, $obligation] as $target) {
                F::check(F::cmp($data['monto'], (new AplicacionFinancieraService)->pending('id_obligacion', $target)) <= 0,
                    'monto', 'La compensacion excede uno de los saldos pendientes');
            }
            if ($withholding) {
                $used = F::sum(\App\Models\Administracion\CompensacionLaboral::where('id_retencion', $advance->id)->where('estado', 'registrado')->get(), 'monto');
                F::check(F::cmp(F::add($used, $data['monto']), $advance->monto) <= 0, 'monto', 'La retencion ya fue descontada del salario');
            }
        } elseif ($resource === 'activos') {
            $data['monto'] = F::mul($data['cantidad'], $data['precio_unitario']);
        } elseif ($resource === 'eventosactivos') {
            $asset = ActivoAdministrativo::findOrFail($data['id_activo']);
            F::active($asset, 'id_activo');
            F::check($data['fecha'] >= $asset->fecha->toDateString(), 'fecha', 'El evento precede a la adquisicion');
            $locations = $this->assetLocations($asset, $data['fecha']);
            $origin = (string) ($data['id_subagencia_origen'] ?? 0);
            F::check(F::cmp($data['cantidad'], $locations[$origin] ?? 0) <= 0, 'cantidad', 'La ubicacion no tiene esa cantidad disponible');
            F::check($data['tipo'] === 'baja' ? empty($data['id_subagencia_destino'])
                : (! empty($data['id_subagencia_destino']) && (int) $data['id_subagencia_destino'] !== (int) ($data['id_subagencia_origen'] ?? 0)),
                'id_subagencia_destino', 'Indique otra ubicacion solo para un traslado');
            F::check(! EventoActivo::where('id_activo', $asset->id)->where('estado', 'registrado')->whereDate('fecha', '>', $data['fecha'])->exists(),
                'fecha', 'Registre los eventos del activo en orden cronologico');
        } elseif ($resource === 'financiamientos') {
            $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
            F::active($account, 'id_cuenta');
            F::check($account->tipo === 'por_pagar' && $account->moneda === $data['moneda'] && F::cmp($account->saldo_inicial, 0) === 0
                && ! MovimientoCuenta::where('id_cuenta', $account->id)->exists(), 'id_cuenta', 'Use una cuenta por pagar nueva, de la misma moneda y sin saldo para no duplicar el principal');
            F::check($account->fecha_apertura->toDateString() <= $data['fecha'], 'fecha', 'La cuenta no existia en esa fecha');
            F::check(empty($data['fecha_vencimiento']) || $data['fecha_vencimiento'] >= $data['fecha'], 'fecha_vencimiento', 'El vencimiento precede al desembolso');
            if (! empty($data['id_operacion_desembolso'])) {
                $operation = OperacionFinanciera::findOrFail($data['id_operacion_desembolso']);
                F::active($operation, 'id_operacion_desembolso');
                F::check($operation->tipo === 'ingreso' && $operation->moneda === $data['moneda']
                    && F::cmp($operation->monto, $data['principal']) === 0 && $operation->fecha->toDateString() <= $data['fecha'],
                    'id_operacion_desembolso', 'El ingreso debe corresponder al principal de esta deuda');
                F::check(! AplicacionFinanciera::where('id_operacion', $operation->id)->where('estado', 'registrado')->exists(),
                    'id_operacion_desembolso', 'El dinero del desembolso ya fue aplicado');
                F::check(! \App\Models\Administracion\MovimientoPersonal::where('id_operacion', $operation->id)->where('estado', 'registrado')->exists(),
                    'id_operacion_desembolso', 'El dinero ya tiene un movimiento personal');
                F::check(! Financiamiento::where('id_operacion_desembolso', $operation->id)->where('estado', 'registrado')->exists(),
                    'id_operacion_desembolso', 'El ingreso ya corresponde a otro financiamiento');
            }
        } elseif ($resource === 'cuotas') {
            $loan = Financiamiento::findOrFail($data['id_financiamiento']);
            F::active($loan, 'id_financiamiento');
            F::check(! \App\Models\Administracion\CuotaFinanciamiento::where('id_financiamiento', $loan->id)->where('numero', $data['numero'])
                ->where('estado', 'registrado')->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists(),
                'numero', 'El financiamiento ya tiene ese numero de cuota vigente');
            F::check($data['fecha'] >= $loan->fecha->toDateString() && (! $loan->numero_cuotas || (int) $data['numero'] <= $loan->numero_cuotas),
                'numero', 'La cuota no corresponde al plazo de este contrato');
            $data['moneda'] = $loan->moneda;
            $data['monto'] = F::add(F::add($data['capital'], $data['interes']), F::add($data['seguro'], $data['comision']));
            F::check(F::cmp($data['monto'], 0) > 0, 'capital', 'La cuota debe tener un importe positivo');
            $query = \App\Models\Administracion\CuotaFinanciamiento::where('id_financiamiento', $loan->id)->where('estado', 'registrado');
            if ($record) {
                $query->whereKeyNot($record->id);
            }
            $scheduled = F::add(F::sum($query->get(), 'capital'), $data['capital']);
            $gross = F::sum(MovimientoCuenta::where('id_cuenta', $loan->id_cuenta)->where('tipo', 'cargo')->where('estado', 'registrado')->get(), 'monto');
            F::check(F::cmp($scheduled, $gross) <= 0, 'capital', 'El calendario excede el principal y ajustes registrados');
        } elseif ($resource === 'incidencias') {
            if (! empty($data['id_envio_dhl'])) {
                $shipment = EnvioDhl::findOrFail($data['id_envio_dhl']);
                F::check($data['servicio'] === 'dhl' && (empty($data['id_arqueo']) || (int) $data['id_arqueo'] === $shipment->id_arqueo), 'id_envio_dhl', 'El envio y arqueo no corresponden');
                $data['id_arqueo'] = $shipment->id_arqueo;
            }
            if (! empty($data['id_arqueo'])) {
                $day = Arqueo::findOrFail($data['id_arqueo']);
                F::check($day->estado !== 'anulado' && $data['fecha'] >= $day->fecha->toDateString(), 'id_arqueo', 'El arqueo debe estar vigente y no ser posterior a la incidencia');
                F::check(empty($data['id_operador']) || (int) $data['id_operador'] === $day->id_usuario, 'id_operador', 'El operador debe corresponder a la jornada');
                F::check(empty($data['id_subagencia']) || (int) $data['id_subagencia'] === $day->id_subagencia, 'id_subagencia', 'La agencia debe corresponder a la jornada');
                $data['id_operador'] = $day->id_usuario;
                $data['id_subagencia'] = $day->id_subagencia;
            }
            if (! empty($data['id_cuenta'])) {
                $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
                F::active($account, 'id_cuenta');
                F::check(in_array($account->tipo, ['por_cobrar', 'por_pagar'], true) && $account->moneda === $data['moneda'], 'id_cuenta', 'La incidencia requiere una cuenta auxiliar de su moneda');
            }
        } elseif ($resource === 'gestionesincidencia') {
            $case = IncidenciaFinanciera::findOrFail($data['id_incidencia']);
            F::active($case, 'id_incidencia');
            F::check($data['fecha'] >= $case->fecha->toDateString()
                && ! GestionIncidencia::where('id_incidencia', $case->id)->where('estado', 'registrado')->whereDate('fecha', '>', $data['fecha'])->exists(),
                'fecha', 'Registre las gestiones en orden cronologico');
            F::check($data['gestion'] !== 'cerrado' || F::cmp((new AplicacionFinancieraService)->pending('id_incidencia', $case), 0) === 0,
                'gestion', 'La confirmacion del proveedor no liquida el pendiente financiero');
        } elseif ($resource === 'aplicaciones') {
            F::check(! DepositoLiquidacion::where('id_operacion', $data['id_operacion'])->where('estado', '!=', 'anulado')->exists(),
                'id_operacion', 'El egreso ya esta declarado como deposito a la matriz');
            $data = (new AplicacionFinancieraService)->prepare($data);
        } elseif ($resource === 'cierres') {
            $data = (new CapitalService)->prepareClose($request, $data);
        }

        return $data;
    }

    private function post(Request $request, string $resource, Model $record): void
    {
        if ($resource === 'operaciones') {
            (new LedgerService)->postOperation($request, $record);
        } elseif ($resource === 'movimientospersonales') {
            (new CuentaPersonalService)->post($request, $record);
        } elseif ($resource === 'arqueosmadre') {
            (new ConsolidacionService)->calculate($request, $record, true);
        } elseif ($resource === 'aplicaciones') {
            (new AplicacionFinancieraService)->post($request, $record);
        } elseif ($resource === 'depositosliquidacion') {
            (new MatrizService)->postDeposit($request, $record);
        } elseif ($resource === 'compensacioneslaborales') {
            $targets = ['id_obligacion' => 'id_abono_obligacion'];
            if ($record->id_anticipo) {
                $targets['id_anticipo'] = 'id_abono_anticipo';
            }
            foreach ($targets as $sourceField => $destinationField) {
                $target = \App\Models\Administracion\ObligacionLaboral::findOrFail($record->$sourceField);
                $ledger = new LedgerService;
                $entry = $ledger->entry($request, CuentaFinanciera::findOrFail($target->id_cuenta), $record->fecha->toDateString(),
                    'abono', $record->monto, 'Compensacion laboral '.$record->id, 'compensacion:'.$record->id.':'.$sourceField);
                $ledger->apply($request, MovimientoCuenta::findOrFail($target->id_movimiento_origen), $entry, $record->fecha->toDateString(), $record->monto);
                $record->update([$destinationField => $entry->id]);
            }
        } elseif ($resource === 'gestionesincidencia') {
            $parent = $record->incidencia;
            $before = $parent->getAttributes();
            $parent->update(['gestion' => $record->gestion]);
            F::audit($request, $parent, 'gestionar', $before);
        } elseif (in_array($resource, ['gastos', 'obligacioneslaborales', 'comisiones', 'financiamientos', 'incidencias'], true) && $record->id_cuenta) {
            $amount = $resource === 'financiamientos' ? $record->principal : $record->monto;
            $type = $resource === 'obligacioneslaborales' && $record->concepto === 'descuento' ? 'abono' : 'cargo';
            $entry = (new LedgerService)->entry($request, CuentaFinanciera::findOrFail($record->id_cuenta), $record->fecha->toDateString(),
                $type, $amount, $resource.' '.$record->id, $resource.':'.$record->id);
            $record->update(['id_movimiento_origen' => $entry->id]);
            if ($resource === 'obligacioneslaborales' && in_array($record->concepto, ['salario', 'aguinaldo', 'vacaciones', 'indemnizacion', 'inss_patronal', 'inatec'], true)) {
                $expense = GastoAdministrativo::create(['fecha' => $record->fecha, 'periodo_desde' => $record->periodo_desde,
                    'periodo_hasta' => $record->periodo_hasta, 'ambito' => 'operativo', 'categoria' => 'laboral_'.$record->concepto,
                    'concepto' => 'Obligacion laboral '.$record->id, 'id_subagencia' => $record->id_subagencia,
                    'moneda' => $record->moneda, 'monto' => $record->monto, 'origen_automatico' => 'obligacioneslaborales:'.$record->id,
                    'id_usuario_registro' => Access::user($request)->id]);
                F::audit($request, $expense, 'clasificar_laboral');
            }
        }
    }

    public function dependencies(Model $record, ?Model $exclude = null): bool
    {
        foreach (Recursos::DEPENDENCIAS as $dependency) {
            if ($dependency['referencia'] !== $record->getTable()) {
                continue;
            }
            $query = DB::table($dependency['tabla'])->where($dependency['campo'], $record->id)->whereNotIn('estado', ['anulado', 'inactivo']);
            if ($exclude && $dependency['tabla'] === $exclude->getTable()) {
                $query->where('id', '!=', $exclude->id);
            }
            if ($record instanceof OperacionFinanciera && $dependency['tabla'] === 'movimientos_cuenta_financiera') {
                $query->where(fn ($q) => $q->whereNull('origen_automatico')->orWhere('origen_automatico', '!=', 'operacion:'.$record->id));
            }
            if ($record instanceof ArqueoMadre && $dependency['tabla'] === 'detalle_arqueo_madre') {
                continue;
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    public function annul(Request $request, string $resource, Model $record): void
    {
        F::check(! in_array($resource, ['historial', 'cierres', 'conciliaciones', 'gestionesincidencia', 'eventosactivos'], true), 'recurso', 'Este registro se conserva como evidencia; registre un evento posterior');
        F::check(! in_array($record->estado, ['cerrado', 'confirmado', 'anulado'], true), 'estado', 'El registro ya no admite anulacion');
        $date = $record->fecha ?? $record->fecha_apertura;
        if ($date) {
            F::openDate($date);
        }
        F::check(! $record->origen_automatico, 'registro', 'Administre el movimiento automatico desde su origen');
        if ($record instanceof MovimientoCuenta) {
            (new LedgerService)->assertEntryMutation($record);
        }
        if ($resource === 'movimientospersonales') {
            (new CuentaPersonalService)->annul($request, $record);
        } elseif ($resource === 'aplicaciones') {
            if ($record->id_incidencia) {
                F::check(IncidenciaFinanciera::findOrFail($record->id_incidencia)->gestion !== 'cerrado',
                    'id_incidencia', 'Registre primero una gestion de reapertura antes de retirar el reembolso');
            }
            if ($record->id_obra) {
                F::check(! AplicacionAnticipoObra::where('id_anticipo', $record->id_obra)->where('estado', 'registrado')->exists(), 'registro', 'Retire primero las aplicaciones del anticipo de obra');
            }
            if ($record->id_movimiento_generado) {
                $entry = MovimientoCuenta::findOrFail($record->id_movimiento_generado);
                foreach (AplicacionCuenta::where('id_abono', $entry->id)->where('estado', 'registrado')->get() as $item) {
                    $before = $item->getAttributes();
                    $item->update(['estado' => 'anulado']);
                    F::audit($request, $item, 'anular_origen', $before);
                }
                $before = $entry->getAttributes();
                (new LedgerService)->assertEntryMutation($entry);
                $entry->update(['estado' => 'anulado']);
                F::audit($request, $entry, 'anular_origen', $before);
            }
            foreach (GastoAdministrativo::where('origen_automatico', 'aplicacion:'.$record->id)->where('estado', 'registrado')->get() as $expense) {
                $before = $expense->getAttributes();
                $expense->update(['estado' => 'anulado']);
                F::audit($request, $expense, 'anular_origen', $before);
            }
        } elseif (in_array($resource, ['depositosliquidacion', 'compensacioneslaborales'], true)) {
            if ($resource === 'depositosliquidacion') {
                F::check($record->liquidacion->estado === 'borrador', 'id_liquidacion', 'El deposito pertenece a una liquidacion cerrada');
                $entries = [$record->id_movimiento_acreditacion, $record->id_movimiento_transito];
            } else {
                $entries = [$record->id_abono_anticipo, $record->id_abono_obligacion];
            }
            foreach (array_filter($entries) as $id) {
                $entry = MovimientoCuenta::findOrFail($id);
                foreach (AplicacionCuenta::where('id_abono', $entry->id)->where('estado', 'registrado')->get() as $item) {
                    $before = $item->getAttributes();
                    $item->update(['estado' => 'anulado']);
                    F::audit($request, $item, 'anular_origen', $before);
                }
                F::check(! $this->dependencies($entry, $record), 'registro', 'El movimiento automatico tiene otras aplicaciones');
                $before = $entry->getAttributes();
                (new LedgerService)->assertEntryMutation($entry);
                $entry->update(['estado' => 'anulado']);
                F::audit($request, $entry, 'anular_origen', $before);
            }
        } else {
            F::check(! $this->dependencies($record), 'registro', 'Hay registros dependientes que deben resolverse antes de anular');
        }
        if ($record->id_movimiento_origen) {
            $entry = MovimientoCuenta::findOrFail($record->id_movimiento_origen);
            F::check(! $this->dependencies($entry, $record), 'registro', 'El cargo original ya tiene aplicaciones');
            $before = $entry->getAttributes();
            (new LedgerService)->assertEntryMutation($entry);
            $entry->update(['estado' => 'anulado']);
            F::audit($request, $entry, 'anular_origen', $before);
        }
        if ($resource === 'operaciones') {
            foreach (MovimientoCuenta::where('origen_automatico', 'operacion:'.$record->id)->where('estado', 'registrado')->get() as $entry) {
                F::check(! $this->dependencies($entry), 'registro', 'La cuenta ya tiene aplicaciones sobre este movimiento');
                $before = $entry->getAttributes();
                (new LedgerService)->assertEntryMutation($entry);
                $entry->update(['estado' => 'anulado']);
                F::audit($request, $entry, 'anular_origen', $before);
            }
        }
        if ($resource === 'aplicacionescuenta') {
            F::check(! $record->abono->origen_automatico, 'registro', 'Retire esta aplicacion desde su origen financiero');
        }
        foreach (GastoAdministrativo::where('origen_automatico', $resource.':'.$record->id)->where('estado', 'registrado')->get() as $expense) {
            $before = $expense->getAttributes();
            $expense->update(['estado' => 'anulado']);
            F::audit($request, $expense, 'anular_origen', $before);
        }
        if ($resource === 'detallesreporte') {
            F::check($record->reporte->estado === 'borrador', 'id_reporte', 'El reporte ya fue confirmado');
        }
        $before = $record->getAttributes();
        $values = ['estado' => Recursos::DEFINICIONES[$resource]['catalog'] ? 'inactivo' : 'anulado'];
        if ($resource === 'operaciones') {
            $values['clave_origen_activa'] = null;
        } elseif ($resource === 'depositosliquidacion') {
            $values['clave_operacion_activa'] = null;
        } elseif ($resource === 'comisiones') {
            $values['clave_externa_activa'] = null;
        }
        $record->update($values);
        F::audit($request, $record, 'anular', $before);
    }

    public function action(Request $request, string $resource, string $action, Model $record): mixed
    {
        if ($resource === 'arqueosmadre') {
            F::check(in_array($action, ['resumen', 'recalcular', 'cerrar'], true), 'accion', 'Accion no disponible');
            if ($action !== 'resumen') {
                F::openDate($record->fecha);
            }

            return (new ConsolidacionService)->calculate($request, $record, $action !== 'resumen', $action === 'cerrar');
        }
        if ($resource === 'reportesmatriz' && $action === 'confirmar') {
            F::openDate($record->fecha);

            return (new MatrizService)->confirm($request, $record);
        }
        if ($resource === 'conciliaciones' && $action === 'revisar') {
            F::openDate($record->arqueo_madre->fecha);
            Validator::make($request->all(), ['motivo' => 'required|string|max:10000'])->validate();
            F::check($record->estado === 'con_diferencias', 'estado', 'Solo se revisan diferencias pendientes');
            $before = $record->getAttributes();
            $record->update(['estado' => 'revisado', 'motivo_revision' => $request->input('motivo'), 'revisado_at' => Carbon::now()]);
            F::audit($request, $record, 'revisar', $before);

            return $record;
        }
        if ($resource === 'liquidaciones' && in_array($action, ['resumen', 'cerrar'], true)) {
            $summary = (new MatrizService)->summary($record);
            if ($action === 'cerrar') {
                F::check($record->estado === 'borrador', 'estado', 'La liquidacion ya no esta abierta');
                F::openDate($record->fecha);
                $before = $record->getAttributes();
                $record->update(['estado' => 'cerrado', 'resultado' => $summary]);
                F::audit($request, $record, 'cerrar', $before);
            }

            return ['Registro' => $record->fresh(), 'Resumen' => $summary];
        }
        if ($resource === 'depositosliquidacion' && $action === 'acreditar') {
            $data = Validator::make($request->all(), ['fecha_acreditacion' => 'required|date_format:Y-m-d'])->validate();
            F::check($record->estado === 'pendiente' && $record->liquidacion->estado !== 'anulado', 'estado', 'El deposito ya fue acreditado o anulado');
            F::check($data['fecha_acreditacion'] >= $record->fecha->toDateString(), 'fecha_acreditacion', 'La acreditacion precede al deposito');
            F::openDate($data['fecha_acreditacion']);
            $before = $record->getAttributes();
            (new MatrizService)->creditDeposit($request, $record, $data['fecha_acreditacion']);
            $record->update(['estado' => 'acreditado', 'fecha_acreditacion' => $data['fecha_acreditacion']]);
            F::audit($request, $record, 'acreditar', $before);

            return $record;
        }
        $data = Validator::make($request->query(), ['fecha' => 'required|date_format:Y-m-d'])->validate();
        if ($resource === 'cuentas' && $action === 'saldo') {
            return (new LedgerService)->balance($record, $data['fecha']);
        }
        if ($resource === 'activos' && $action === 'inventario') {
            return ['Registro' => $record, 'fecha_corte' => $data['fecha'], 'ubicaciones' => $this->assetLocations($record, $data['fecha'])];
        }
        if ($resource === 'proyectos' && $action === 'resumen') {
            return (new CapitalService)->project($record, $data['fecha']);
        }
        if ($resource === 'financiamientos' && $action === 'resumen') {
            return ['Registro' => $record, 'Cuenta' => (new LedgerService)->balance($record->cuenta, $data['fecha']),
                'Cuotas' => $record->getConnection()->table('cuotas_financiamiento')->where('id_financiamiento', $record->id)->orderBy('numero')->get()];
        }
        if ($resource === 'incidencias' && $action === 'resumen') {
            return ['Registro' => $record, 'Pendiente' => (new AplicacionFinancieraService)->pending('id_incidencia', $record, $data['fecha']),
                'Gestiones' => GestionIncidencia::where('id_incidencia', $record->id)->whereDate('fecha', '<=', $data['fecha'])->orderBy('fecha')->orderBy('id')->get()];
        }
        abort(404);
    }

    public function assetLocations(ActivoAdministrativo $asset, string $date): array
    {
        if ($asset->fecha->toDateString() > $date) {
            return [];
        }
        $locations = [(string) ($asset->id_subagencia ?? 0) => $asset->cantidad];
        foreach (EventoActivo::where('id_activo', $asset->id)->where('estado', 'registrado')->whereDate('fecha', '<=', $date)->orderBy('fecha')->orderBy('id')->get() as $event) {
            $from = (string) ($event->id_subagencia_origen ?? 0);
            $locations[$from] = F::sub($locations[$from] ?? 0, $event->cantidad);
            if ($event->tipo === 'traslado') {
                $to = (string) $event->id_subagencia_destino;
                $locations[$to] = F::add($locations[$to] ?? 0, $event->cantidad);
            }
        }

        return $locations;
    }
}

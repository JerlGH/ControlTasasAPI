# API de consolidación y administración financiera

La referencia modular de la terminal LAFISE/BANPRO está en [API_SERVICIOS_EXTERNOS.md](API_SERVICIOS_EXTERNOS.md). El contrato completo, incluidos sus cambios en comisiones y capital, está incorporado en V4.

Para entregar el contrato completo al desarrollo C#, usar [INTEGRACION_API_CSHARP_V4.md](INTEGRACION_API_CSHARP_V4.md), que integra esta referencia, caja, cuentas personales, Diario, terminal y cambios de compatibilidad.

Contrato del 5 de noviembre de 2026. Complementa [la API de caja](API_CAJA.md) y [la integración C#](INTEGRACION_API_CAJA_CSHARP.md). Implementa los procesos administrativos identificados en **Arqueo diario (1).xlsx**, utilizando los registros individuales del módulo de caja.

Los depósitos y retiros de fondos personales, el reintegro de sus saldos negativos y los préstamos entregados a particulares están implementados mediante `movimientospersonales`. Su flujo completo y ejemplos están en [API_CUENTAS_PERSONALES.md](API_CUENTAS_PERSONALES.md).

## 1. Alcance y conexión con caja

La consolidación toma jornadas de una fecha exacta, agrupadas por la agencia que conserva cada arqueo. Calcula contado, utilidad, compras/ventas de USD, WU, Biller y diferencias físicas. La comparación con matriz y la liquidación son procesos separados: una diferencia de servicio no se convierte automáticamente en faltante de un cajero.

Las cuentas bancarias/POS y auxiliares se registran en tablas nuevas. Una **operación financiera** representa el dinero recibido, pagado o trasladado; una **aplicación financiera** explica a qué gasto, cargo, comisión, activo, cuota o incidencia corresponde. Aplicar un pago no mueve el banco por segunda vez.

Los registros de caja ya cerrados pueden vincularse como origen. El enlace conserva su importe, moneda, ID y una copia de sus datos; no escribe sobre la jornada ni sobre la operación original. Los nuevos procesos no modifican `users`, `trabajadores`, `clientes`, `subagencias` ni `mesas_cambio`.

La captura de los datos administrativos y del reporte de matriz es explícita. No hay importador de Excel, búsqueda de libros externos, sincronización con AirPak, carga de archivos ni generación de hojas de cálculo. `documentos` conserva metadatos y SHA-256 de una evidencia que ya existe; no almacena ni descarga su contenido.

## 2. Autenticación, valores y respuestas

Todas las rutas siguientes llevan **`/api/administracion`**, usan Sanctum y requieren un usuario activo con rol **Administrador**. Los cajeros continúan utilizando las rutas individuales anteriores. Enviar:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Usar IDs existentes, fechas `YYYY-MM-DD`, monedas exactamente `NIO` o `USD`, y cantidades monetarias como cadenas decimales con punto. Los importes administrativos admiten hasta 16 enteros y 8 decimales; las tasas NIO por USD, hasta 6 enteros y 6 decimales. Las cantidades de activos y obra admiten 6 decimales. La API usa aritmética decimal; conversiones/divisiones derivadas se redondean `HALF_UP` a 8 decimales. Esta precisión técnica no establece una política de pago en centavos.

No enviar resultados calculados, autor, estado de cierre ni claves internas. Los campos no admitidos se rechazan con 422. El estado de un catálogo sí puede ser `activo` o `inactivo`.

| Petición | Respuesta |
| --- | --- |
| GET listado | `{"Registros":[],"Paginacion":{"total":0,"pagina":1,"por_pagina":50},"Status":200}` |
| GET detalle; PATCH/PUT; DELETE | `{"Registro":{...},"Status":200}` |
| POST alta | HTTP 201; `{"Registro":{...},"Status":201}` |
| Acción; GET capital | `{"Resultado":{...},"Status":200}` |

Los listados aceptan `page`, `per_page` (1–100), `fecha_desde`, `fecha_hasta`, y los campos `id_*` presentes en el modelo. También filtran por `moneda`, `tipo`, `estado`, `servicio`, `naturaleza`, `rubro_capital`, `gestion`, `entidad`, `referencia` o `codigo` cuando pertenecen al recurso. Los filtros son por igualdad. Los estados anulados se incluyen salvo que se filtre `estado`. Las fechas filtran `fecha`, `fecha_apertura` o, si no existen, `created_at`. No asumir que un filtro desconocido se aplicó.

Errores: 401 sin sesión, 403 usuario inactivo o sin permiso, 404 ID/ruta inexistente, 422 validación o regla de negocio, 409 duplicado o restricción de integridad. Las validaciones usan el formato JSON de Laravel (`message`, `errors`). No depender del texto exacto traducido del error.

PUT y PATCH son actualizaciones parciales. Las relaciones y las identidades (`id_*`, `codigo`, `clave`, origen) son inmutables. Los datos financieros contabilizados se corrigen anulando y creando el registro correcto cuando el periodo esté abierto. Los catálogos se desactivan; los demás registros se anulan sin borrar la evidencia. DELETE exige `{"motivo":"Explicación"}`. No se anulan cierres, conciliaciones, eventos de activos ni gestiones de incidencias; estos conservan su evidencia. Un reporte confirmado y un consolidado o liquidación cerrados quedan inmutables.

Una operación con `clave` repetida se rechaza, no devuelve automáticamente el alta anterior. Tras una desconexión, consultar por los identificadores conocidos y verificar el resultado antes de repetir una captura. Las aplicaciones, compensaciones y acciones no tienen una clave general de idempotencia del cliente.

## 3. Consolidación diaria y reporte de matriz

1. Cerrar los arqueos individuales con sus datos completos.
2. POST `arqueosmadre`, indicando fecha, tasa y lista explícita de usuarios esperados. Omitir `id_subagencia` para todo el grupo; informarla para una agencia. No se deducen los cajeros esperados a partir de los usuarios actuales.
3. Consultar `arqueosmadre/{id}/resumen`; un ausente o un arqueo abierto aparece como pendiente. `recalcular` guarda una nueva lectura del borrador. `cerrar` exige que todos los previstos estén cerrados y completos, y guarda su versión y detalle.
4. Crear `reportesmatriz`, sus cuatro `detallesreporte` y ejecutar `confirmar`. Deben existir WU y Biller para NIO y USD, incluso si sus importes son cero.
5. Crear `conciliaciones` con el consolidado cerrado y el reporte confirmado de la misma fecha y alcance. Cada línea conserva **interno − matriz**. El resultado es `conciliado` o `con_diferencias`.
6. Si se revisa una diferencia, POST `conciliaciones/{id}/revisar` con motivo. El estado pasa a `revisado`; la diferencia original sigue guardada.

```json
{
  "fecha": "2026-11-05",
  "tasa_referencia": "36.74",
  "usuarios_esperados": [12, 18, 23],
  "observacion": "Personal previsto para el corte"
}
```

El POST anterior es ilustrativo: sustituir los IDs por los existentes. El alta devuelve un borrador con cobertura y totales preliminares. Las versiones se numeran por fecha y alcance; un grupo y una agencia son alcances distintos. No sumar sus totales entre sí: podrían incluir las mismas jornadas.

En `arqueosmadre/{id}/resumen`, `recalcular` y `cerrar`, `Resultado` contiene `Registro`, `Totales` y `Fuentes`. `Totales` incluye `completo`, `cobertura`, `metricas` y `por_agencia`. Cada fuente conserva el ID y estado de la jornada, los datos usados y su huella.

| Métrica | Regla |
| --- | --- |
| Contado NIO/USD | Conteo final combinado de efectivo y cheques de cada jornada; no sumar otra vez los cheques. |
| Diferencia NIO/USD | Contado − esperado, separado por moneda. |
| WU NIO | Envíos NIO − pagos NIO. |
| WU USD | Pagos USD − envíos USD; perspectiva de liquidación del Excel. |
| Biller NIO/USD | Total declarado con su signo, no saldo de una plataforma. |
| Compras/ventas USD | Volumen USD de las operaciones de cambio vigentes. |
| Utilidad USD | Margen NIO aplicado y ganancia de cheques NIO / referencia individual, más ganancia de cheques USD. Si falta referencia individual, se utiliza la del consolidado y se identifica esa elección en el detalle. |
| Proyección NIO | `(WU_USD − Biller_USD) × TC − (WU_NIO + Biller_NIO)`. |

Los importes NIO efectivamente aplicados se usan para el margen cuando existen, conservando las fracciones del registro individual. No se consulta una cotización actual para recalcular una jornada histórica.

## 4. Liquidación y depósitos en tránsito

POST `liquidaciones` requiere `id_arqueo_madre` cerrado. La primera liquidación de cada alcance necesita `saldo_anterior_usd` explícito, incluso cero, y `motivo_saldo_inicial`. Las siguientes heredan **el negativo del pendiente** del cierre anterior del mismo alcance. No se permite saltarse un borrador anterior ni reemplazar ese arrastre.

```text
proyección_USD = proyección_NIO / TC − saldo_anterior_USD − anticipo_USD − comisión_anticipo_USD
pendiente_USD = proyección_USD + depósitos_acreditados_USD
```

`anticipo_usd` y `comision_anticipo_usd` son datos declarados, por defecto cero. Para comparar el saldo comunicado se deben enviar juntos `saldo_matriz_nio`, `saldo_interno_nio` y `corte_comparacion_at` de esa fecha. La diferencia de esos saldos no sustituye la conciliación de cuatro totales.

Un depósito utiliza un `egreso` financiero real, sin aplicaciones previas. POST `depositosliquidacion` lleva liquidación, operación y fecha. Mientras esté pendiente exige `id_cuenta_transito`: cuenta **por cobrar**, de la misma moneda y con `rubro_capital: pendiente`. Se genera un cargo por el dinero aún no acreditado. Si se informa `fecha_acreditacion` al crearlo, debe estar dentro del corte.

POST `depositosliquidacion/{id}/acreditar` recibe `{"fecha_acreditacion":"2026-11-06"}`. Genera el abono y su aplicación contra el cargo de tránsito; no genera otro egreso bancario. Puede ocurrir después del cierre de su liquidación, con fecha de efecto de un periodo financiero abierto. El saldo de tránsito del corte anterior se conserva, y la acreditación se reconoce una vez en la siguiente liquidación del mismo alcance. La conversión del depósito a USD utiliza la tasa del corte donde se reconoce.

POST `liquidaciones/{id}/cerrar` guarda `resultado` y devuelve `Resultado.Registro` y `Resultado.Resumen`. Los resúmenes posteriores de esa liquidación devuelven la fotografía cerrada; para el estado actual consultar también los depósitos y el siguiente corte. No se permite anular un depósito de una liquidación cerrada.

## 5. Cuentas, operaciones y aplicaciones

`contrapartes` separa proveedores, propietarios, colaboradores, bancos y otras personas del catálogo protegido de clientes. Una cuenta tiene naturaleza, moneda, apertura, saldo inicial y clasificación explícita para capital. La clasificación por defecto es `excluido`.

| Tipo de cuenta | Rubros permitidos | Efecto de saldo en capital |
| --- | --- | --- |
| banco / pos | banco, excluido | Se agrega en su moneda si está en banco. |
| por_cobrar / recargas | pendiente, excluido | Se agrega al pendiente neto. |
| por_pagar | pendiente, deuda, excluido | Se resta como pendiente o como deuda. |
| fondo | pendiente, excluido | Se resta como fondos de terceros; un saldo negativo representa saldo a favor del negocio. |
| liquidacion | excluido | La liquidación se toma de su módulo, evitando duplicarla. |

El saldo es `inicial + cargos − abonos` a la fecha solicitada. Desactivar una cuenta no borra su saldo histórico del reporte. Un saldo inicial agregado no constituye un cargo individual al cual aplicar un cobro: para cobrar partidas heredadas, capturarlas como cargos identificados, sin duplicarlas también en el inicial.

Los ajustes auxiliares manuales se crean en `movimientoscuenta` con `motivo`, `clave`, cargo/abono, importe, cuenta y fecha. No admiten banco/POS ni la asignación manual de `id_operacion`. `aplicacionescuenta` cruza un abono manual con un cargo manual o automático de la misma cuenta, dentro de sus pendientes; no mueve dinero. Los abonos automáticos se administran desde su origen. Para pagar un cargo manual mediante banco/caja, utilizar `aplicaciones` con `id_cargo`; los documentos automáticos conservan sus destinos específicos.

Las aplicaciones monetarias contra cargos o documentos con cargo asociado comprueban el saldo neto de la cuenta, además del pendiente individual, a la fecha de aplicación y al considerar movimientos posteriores existentes. Una reducción manual de 20 sobre deuda de 100 impide pagar 100; al imputar ese abono al cargo automático y pagar 80, saldo y pendiente quedan en cero. Se conserva la clasificación nominal del gasto: una reducción de deuda no equivale automáticamente a reducir ese gasto.

Una operación bancaria manual de ingreso necesita solamente destino; un egreso, solamente origen; un traslado, ambas cuentas distintas. Deben ser banco/POS de la misma moneda y ya abiertas en esa fecha. La operación genera sus movimientos de cuenta automáticamente. El modelo no impone una prohibición general de sobregiro bancario.

Para reconocer una operación ya capturada por un cajero, enviar `origen_tipo` e `id_origen`:

| origen_tipo | Fuente |
| --- | --- |
| entrada_salida | `entradas_y_salidas` |
| pase | `pases` |
| remesa | `remesas_bancarias` |
| cambio_entrada / cambio_salida | Pata correspondiente de `operaciones_cambio_caja`. |

La jornada fuente debe estar cerrada, el movimiento vigente y el importe/moneda deben coincidir exactamente. Una fuente marcada como transferencia bancaria no representa una nueva entrega física y no admite este reconocimiento. Un ingreso/egreso de caja no lleva cuenta bancaria adicional. Si es traslado de caja hacia banco, usar `tipo: transferencia` y solo el destino bancario; en la dirección contraria, solo el origen. No se cambia el movimiento original para clasificarlo.

Solo puede existir un reconocimiento activo por fuente/pata. Anularlo libera esa fuente para una clasificación correcta con otra `clave`, conservando el reconocimiento anulado. No se puede anular una operación mientras tenga aplicaciones, depósitos o desembolsos asociados.

POST `aplicaciones` exige operación, fecha, `monto` en la moneda de la operación y **exactamente un destino**:

```json
{
  "id_operacion": 81,
  "fecha": "2026-11-05",
  "monto": "367.40",
  "tasa_cambio": "36.74",
  "id_gasto": 16
}
```

En este ejemplo la operación paga NIO 367.40 y el gasto es USD 10. La API calcula `monto_destino`. La tasa es obligatoria si las monedas difieren; no se envía `monto_destino`. La suma aplicada no puede superar ni la operación ni el pendiente del destino. Se permiten aplicaciones parciales; los traslados no pagan gastos o deudas. Anular una aplicación revierte los abonos y clasificaciones que generó, manteniendo el movimiento de dinero original disponible para reclasificar.

Destinos: `id_gasto`, `id_obligacion`, `id_comision`, `id_obra`, `id_activo`, `id_cuota`, `id_financiamiento`, `id_incidencia` o `id_cargo`. `id_cargo` admite cargos manuales y cargos generados por préstamos personales vigentes; los demás cargos automáticos usan su destino específico. No duplicar un gasto manual y un cargo auxiliar por el mismo hecho esperando que una aplicación a uno cancele también el otro. Los conceptos con obligación auxiliar propia generan su cargo automáticamente. La disponibilidad del dinero también descuenta los movimientos personales activos de la misma operación.

## 6. Gastos, obligaciones laborales y comisiones

`gastos` registra fecha, periodo, ámbito administrativo/operativo/personal, categoría, concepto, importe y moneda; permite contraparte, agencia y centro de costo. Reconocer el gasto no implica que ya se haya pagado. Su pago se vincula desde `aplicaciones`. Si el periodo de un gasto cruza el intervalo del informe de capital, aparece como pendiente de distribución; el sistema no inventa un prorrateo.

Si el gasto queda por pagar, informar `id_cuenta` de una CxP de la misma moneda: se genera su cargo y `id_movimiento_origen`. Aplicar el pago a `id_gasto` reduce automáticamente esa deuda y permite pagos parciales; no crear otro cargo manual por el mismo gasto. La cuenta es opcional para capturas que no requieren auxiliar individual.

`obligacioneslaborales` conserva trabajador, periodo, concepto, importe, moneda y cuenta auxiliar. Un anticipo usa CxC; salario, prestaciones y otras obligaciones usan CxP. Salario, aguinaldo, vacaciones, indemnización, INSS patronal e INATEC generan una clasificación automática de gasto. El anticipo y el INSS laboral no generan ese gasto adicional. `descuento` no se captura como obligación: se registra mediante compensación. No hay cálculo legal de nómina ni tasas legales precargadas.

Al entregar un anticipo, registrar su egreso bancario o reconocer su salida de caja y vincularlo mediante `id_operacion_anticipo`. Debe ser el importe completo, de la misma moneda, sin otra clasificación; queda reservado para ese anticipo. El enlace es opcional para admitir saldos heredados identificados documentalmente. Recuperar un anticipo en dinero utiliza una aplicación de ingreso contra `id_obligacion`.

POST `compensacioneslaborales` cruza `id_anticipo` e `id_obligacion` de un mismo trabajador y moneda, por fecha e importe. Disminuye CxC del anticipo y CxP de la obligación **sin nuevo movimiento de dinero**. El salario restante se paga con un egreso y una aplicación; no volver a clasificar como gasto el anticipo ni la compensación.

Para descontar INSS laboral de un salario bruto, usar `id_retencion` en lugar de `id_anticipo`, junto con `id_obligacion` del salario. La retención debe ser una obligación `inss_laboral` del mismo trabajador y moneda. Esta compensación disminuye solo el salario pendiente: conserva la obligación de INSS para su pago posterior al destinatario. Así, salario bruto 500 y retención 50 dejan salario por pagar 450 e INSS por pagar 50, con gasto salarial 500; no dos gastos ni una deuda salarial adicional. Se permiten descuentos parciales sin superar el importe de la retención. La tasa y el importe de la retención son datos declarados.

`comisiones` distingue proveedor/operador/bancaria y naturaleza cobrar/pagar, periodo, servicio y beneficiario. Genera un cargo en CxC o CxP de su moneda. El cobro o pago utiliza una operación y una aplicación. El indicador global descuenta solo las comisiones de proveedor por cobrar que hayan sido efectivamente aplicadas como cobradas dentro del periodo, no toda comisión devengada ni todo ingreso sin aplicar.

## 7. Obras, activos y financiamientos

`proyectos` identifica una obra. Sus `movimientosobra` separan costo, anticipo, recuperación y devolución. Admiten importe o cantidad/precio; si se informa cantidad o precio, deben enviarse ambos. El importe explícito se conserva; si falta, se deriva del producto. No se usa un alquiler recuperado para modificar silenciosamente el costo bruto.

`aplicacionesobra` cruza un anticipo **ya pagado** con un costo certificado, del mismo proyecto y moneda, sin otro egreso. El reporte utiliza `costo + anticipo − anticipo_aplicado − recuperación − devolución`. Los pagos de costos, anticipos y recuperaciones se documentan mediante `aplicaciones`; el registro de obra conserva el reconocimiento aunque aún no esté pagado.

`activos` conserva costo unitario, cantidad y moneda de adquisición; `monto` se calcula en el servidor. `eventosactivos` registra traslados o bajas con cantidades por ubicación, en orden cronológico. La consulta `inventario` muestra las cantidades al corte; el indicador global utiliza solo cantidades vigentes por su costo registrado. No hay depreciación automática, reclasificación automática de obra a edificio, ni cambio automático de costo histórico. Si se quiere conservar una valoración histórica ya fijada en NIO, registrar ese costo en NIO con su evidencia.

`financiamientos` requiere una cuenta CxP nueva de la misma moneda, inicial cero y sin movimientos para no duplicar el principal. Registra contrato, principal, tasa informativa/base, periodicidad y vencimiento. Puede vincular `id_operacion_desembolso` a un ingreso real por el principal completo, reservado para ese contrato; la deuda también puede ser heredada, sin ese enlace.

`cuotas` recibe el calendario pactado y sus componentes capital/interés/seguro/comisión. El servidor calcula el total y evita programar más principal que el registrado. La tasa del contrato no genera un calendario automáticamente. No se presupone sistema francés, interés simple ni otra fórmula de amortización.

Una aplicación a `id_cuota` reduce la deuda **solo por el capital**. Interés/seguro/comisión generan un gasto financiero automático en la fecha del pago. Un pago parcial exige `componentes` con los cuatro importes, cuya suma equivale a `monto_destino`; un pago por el saldo completo puede omitirlos. Se puede abonar directamente principal mediante `id_financiamiento`; no se reprograman automáticamente las cuotas tras ese abono.

## 8. Incidencias y recuperación de servicios

`incidencias` conserva servicio, importe/moneda, causa, Biller, referencia del cliente, caso/ticket y documento. Puede vincular jornada, operador y envío DHL. Si hay jornada, el operador y la agencia corresponden a esa jornada; si hay envío, debe corresponder al servicio DHL y a la misma jornada. Una cuenta CxC/CxP opcional genera el cargo correspondiente.

`gestionesincidencia` registra eventos de solicitud, confirmación, apertura o cierre y actualiza el estado de gestión del caso. Confirmar una anulación con el proveedor **no registra un reembolso**. Para registrar el dinero recuperado/pagado, crear la operación y aplicarla a `id_incidencia`. El caso solo admite gestión de cierre con pendiente financiero cero. Para retirar una aplicación de reembolso de un caso cerrado, registrar primero una gestión de reapertura, en periodo abierto.

## 9. Indicadores globales y cierre financiero

GET `capital` requiere `fecha`, `periodo_desde`, `id_arqueo_madre` de grupo completo/cerrado e `id_liquidacion` cerrada del mismo corte. Responde con componentes, valores fuente e indicador `capital_grupo_ajustado_usd`, interpretación funcional de `00!I58` («GRAN TOTAL USD»). Es un control de capital ajustado que incorpora gastos y saldos/activos; no es una medición de utilidad disponible.

```text
neto_NIO = contado + bancos + pendientes_netos + gastos + activos + obra_neta
           − WU_NIO − Biller_NIO − comisiones_proveedor_cobradas_NIO − deudas_NIO
AirPak_USD = WU_USD − saldo_anterior_USD − anticipo_USD − comisión_anticipo_USD
neto_USD = contado + bancos + pendientes_netos + gastos + activos + obra_neta
           + AirPak_USD − deudas_USD − Biller_USD − comisiones_proveedor_cobradas_USD
capital_grupo_ajustado_USD = neto_USD + neto_NIO / TC
```

Saldos, activos y obra se consultan a la fecha; gastos y comisiones cobradas corresponden al periodo indicado. La fórmula extiende ambos componentes a las dos monedas para no descartar adquisiciones/gastos USD. Las cuentas excluidas con saldo se muestran expresamente en `cuentas_excluidas`. Los depósitos pendientes figuran mediante su cuenta de tránsito; no se agregan una segunda vez como dinero bancario.

Para el indicador `00!I62`, se puede enviar `cuentas_capital_trabajo[]` con los IDs de los bancos/POS que deben intervenir. Entonces `capital_trabajo_excel` conserva selección, saldos y valor:

```text
(contado_NIO + bancos_seleccionados_NIO)/TC
 + contado_USD + bancos_seleccionados_USD + proyección_USD
```

Sin selección, devuelve `null`: no se supone que todos los bancos participaban en la fórmula del archivo. La selección se admite también al crear el cierre y queda en su fotografía.

POST `cierres` utiliza los mismos cuatro parámetros, `motivo` y `confirmar_fuentes: true`. Requiere una conciliación cuya última revisión esté conciliada o revisada, y gastos con periodos completamente distribuidos. Guarda datos, fuentes, versión de fórmula y SHA-256. La confirmación declara que el administrador revisó la cobertura de las fuentes; no prueba una importación automática de todos los libros.

Los cierres avanzan en fecha. Desde el cierre, no se crean, anulan ni cambian datos financieros con fecha igual o anterior; tampoco se puede mover a una fecha futura un registro del periodo cerrado. Los ajustes se capturan en un periodo abierto. Las fotografías anteriores permanecen inmutables. No hay reapertura de un cierre financiero mediante API.

## 10. Acciones y consultas adicionales

Todas estas respuestas están bajo `Resultado`. Las consultas a cuentas/activos/proyectos/financiamientos/incidencias requieren `?fecha=YYYY-MM-DD`.

| Método y ruta relativa | Cuerpo adicional | Resultado |
| --- | --- | --- |
| GET `arqueosmadre/{id}/resumen` | — | Registro, Totales y Fuentes. |
| POST `arqueosmadre/{id}/recalcular` | — | Guarda la lectura del borrador. |
| POST `arqueosmadre/{id}/cerrar` | — | Cierra el consolidado completo. |
| POST `reportesmatriz/{id}/confirmar` | — | Reporte confirmado. |
| POST `conciliaciones/{id}/revisar` | `motivo` | Conciliación revisada sin borrar diferencias. |
| GET `liquidaciones/{id}/resumen` | — | Registro y Resumen. |
| POST `liquidaciones/{id}/cerrar` | — | Fotografía de liquidación. |
| POST `depositosliquidacion/{id}/acreditar` | `fecha_acreditacion` | Depósito actualizado. |
| GET `cuentas/{id}/saldo` | — | Cuenta, fecha_corte, saldo y movimientos. |
| GET `activos/{id}/inventario` | — | Registro, fecha_corte y ubicaciones (clave 0 = sin agencia). |
| GET `proyectos/{id}/resumen` | — | Registro, fecha_corte y Totales por moneda. |
| GET `financiamientos/{id}/resumen` | — | Registro, Cuenta al corte y Cuotas del contrato. |
| GET `incidencias/{id}/resumen` | — | Registro, Pendiente y Gestiones hasta el corte. |
| GET `capital` | Parámetros de consulta de la sección 9. | Componentes, fuentes e indicadores. |

La consulta de financiamiento muestra el calendario completo; no interpreta todas sus cuotas como vencidas a la fecha de saldo. La ficha de incidencia contiene el estado de gestión actual y el historial filtrado, mientras que el pendiente se calcula al corte solicitado.

## 11. Consumo desde C#

El inicio de sesión y el token son los de [la guía del cliente](INTEGRACION_API_CAJA_CSHARP.md). Este módulo utiliza claves uniformes distintas de las claves particulares de algunos endpoints antiguos de caja. Usar `decimal`, cultura invariante y lectura de números JSON entre comillas; no convertir importes a `double`. No recalcular saldos ni escribir campos derivados en el cliente.

```csharp
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;

// http.BaseAddress debe terminar en /api/.
http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
http.DefaultRequestHeaders.Accept.ParseAdd("application/json");
using var response = await http.PostAsJsonAsync("administracion/arqueosmadre", new
{
    fecha = "2026-11-05",
    tasa_referencia = "36.74",
    usuarios_esperados = new[] { 12, 18, 23 }
});
string body = await response.Content.ReadAsStringAsync();
if (!response.IsSuccessStatusCode)
    throw new InvalidOperationException($"HTTP {(int)response.StatusCode}: {body}");
using var json = JsonDocument.Parse(body);
long id = json.RootElement.GetProperty("Registro").GetProperty("id").GetInt64();
// GET administracion/arqueosmadre/{id}/resumen -> Resultado.Totales.
// POST administracion/arqueosmadre/{id}/cerrar -> Resultado.Registro y Resultado.Totales.
```

Una pantalla administrativa puede seguir el orden consolidado → reporte → conciliación → liquidación/depósitos → revisión de capital → cierre financiero. Cuentas, gastos y los demás auxiliares alimentan ese cierre. Las peticiones son transacciones individuales, no un guardado global de todas las pestañas.

## 12. Implementación, esquema y validación

Las rutas están en [routes/administracion.php](../routes/administracion.php), cargadas dentro del grupo Sanctum existente. [AdministracionController](../app/Http/Controllers/Api/AdministracionController.php) proporciona el contrato común; los servicios de [administración](../app/Services/Administracion) separan consolidación, matriz, cuentas, aplicaciones y capital. [Recursos.php](../app/Support/Administracion/Recursos.php) define las capturas admitidas y sus validaciones. Los modelos están en [Models/Administracion](../app/Models/Administracion).

Se completaron las dos migraciones vacías de `arqueo_madre` y `detalle_arqueo_madre`, y se agregaron las migraciones administrativas `2026_11_05_000000`, `000001`, `000002` y `000003` (movimientos personales). Las migraciones protegidas no se editaron. **No se ejecutaron migraciones en una base real.** Las definiciones modificadas corresponden a tablas que el usuario indicó sin datos en producción; si otra instalación ya ejecutó un esquema anterior, se requiere adaptar ese esquema mediante migraciones incrementales antes de desplegar.

```powershell
php tests/Schema/caja_migrations.php
php tests/Schema/administracion_api.php
```

La segunda prueba incluye la regresión de caja. Ambas usan SQLite en memoria y datos ficticios; la primera también compila DDL MySQL con una conexión bloqueada, sin servidor. Verifican creación/reversión, claves foráneas, protección de maestros, permisos, cálculos, aplicaciones parciales, compensaciones, depósitos posteriores y cierres. No sustituyen una validación en MySQL aislado ni una prueba del login/Sanctum o del cliente de escritorio.

Quedan como integraciones posteriores el importador de los libros, el detalle transaccional del reporte externo, la conciliación bancaria contra extractos y la exportación de reportes. No se inventaron reglas para convertir automáticamente sobrantes/faltantes mixtos a una deuda NIO, calcular nómina legal, depreciar activos ni amortizar préstamos. Los movimientos y conceptos identificados sí pueden capturarse con moneda, fecha, naturaleza y evidencia explícitas.

## 13. Referencia de recursos y campos de captura

Para cada recurso se admiten GET listado/detalle y POST alta, excepto `historial` (solo lectura). PATCH/PUT y DELETE tienen las restricciones de estado y dependencias descritas; `cierres` solo admite alta y lectura. `gestionesincidencia` y `eventosactivos` se conservan como eventos y no admiten cambios financieros ni anulación. El detalle del consolidado es interno y se consulta mediante su resumen.

En las tablas siguientes, «requerido» se refiere a POST; en PATCH/PUT se omiten los campos que no cambian. «Opcional» permite omisión; solo se acepta `null` si se indica. Los campos calculados/internos no forman parte del cuerpo de captura. `motivo` se acepta para auditoría en las capturas y es obligatorio en DELETE, ajustes manuales y cierres según lo descrito. Los IDs y timestamps de respuesta no deben reenviarse como cuerpo completo.


### movimientospersonales

Modelo `MovimientoPersonal`; tabla `movimientos_personales`. Ver [flujo, desgloses y ejemplos](API_CUENTAS_PERSONALES.md).

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `clave` | Requerido | Texto único, hasta 100 caracteres. |
| `id_cuenta` | Requerido | Fondo para depósito/retiro o CxC para préstamo, con contraparte vigente. |
| `id_operacion` | Requerido | Ingreso para depósito; egreso para retiro/préstamo; dinero disponible. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`, en orden cronológico de la cuenta. |
| `tipo` | Requerido | `deposito`, `retiro`, `prestamo_entregado`. |
| `monto` | Requerido | Importe positivo en moneda de la operación, hasta 8 decimales. |
| `tasa_cambio` | Opcional, admite null | Obligatoria entre monedas distintas; NIO por USD, hasta 6 decimales. |
| `concepto` | Requerido | Texto, hasta 1000 caracteres. |
| `id_documento` | Opcional, admite null | Documento vigente. |
| `observacion` | Opcional, admite null | Texto, hasta 10000 caracteres. |

### arqueosmadre

Modelo `ArqueoMadre`; tabla `arqueo_madre`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `tasa_referencia` | Requerido | Decimal, hasta 6 decimales; positivo. |
| `usuarios_esperados` | Requerido | Array no vacío de IDs de usuarios distintos. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### contrapartes

Modelo `ContraparteFinanciera`; tabla `contrapartes_financieras`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `codigo` | Requerido | Texto; máximo 60 caracteres. |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `tipo` | Requerido | `persona`, `empresa`, `banco`, `proveedor`, `propietario`, `colaborador`, `otro`. |
| `id_cliente` | Opcional, admite null | ID de `clientes`. |
| `id_trabajador` | Opcional, admite null | ID de `trabajadores`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `estado` | Opcional | `activo`, `inactivo`; por defecto `activo`. |

### documentos

Modelo `DocumentoFinanciero`; tabla `documentos_financieros`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `referencia` | Opcional, admite null | Texto; máximo 255 caracteres. |
| `ubicacion` | Requerido | Texto; máximo 2000 caracteres. |
| `sha256` | Requerido | Texto; máximo 64 caracteres. |
| `tipo` | Requerido | Texto; máximo 100 caracteres. |
| `fecha_documento` | Opcional, admite null | Fecha `YYYY-MM-DD`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### cuentas

Modelo `CuentaFinanciera`; tabla `cuentas_financieras`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `codigo` | Requerido | Texto; máximo 60 caracteres. |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `tipo` | Requerido | `banco`, `pos`, `por_cobrar`, `por_pagar`, `fondo`, `recargas`, `liquidacion`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `id_contraparte` | Opcional, admite null | ID de `contrapartes_financieras`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `rubro_capital` | Opcional | `banco`, `pendiente`, `deuda`, `excluido`; por defecto `excluido`. |
| `fecha_apertura` | Requerido | Fecha `YYYY-MM-DD`. |
| `saldo_inicial` | Requerido | Decimal, hasta 8 decimales. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `estado` | Opcional | `activo`, `inactivo`; por defecto `activo`. |

### operaciones

Modelo `OperacionFinanciera`; tabla `operaciones_financieras`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `tipo` | Requerido | `ingreso`, `egreso`, `transferencia`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `concepto` | Requerido | Texto; máximo 1000 caracteres. |
| `clave` | Requerido | Texto; máximo 100 caracteres. |
| `id_cuenta_origen` | Opcional, admite null | ID de `cuentas_financieras`. |
| `id_cuenta_destino` | Opcional, admite null | ID de `cuentas_financieras`. |
| `origen_tipo` | Opcional, admite null | `entrada_salida`, `pase`, `remesa`, `cambio_entrada`, `cambio_salida`. |
| `id_origen` | Opcional, admite null | Entero. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### movimientoscuenta

Modelo `MovimientoCuenta`; tabla `movimientos_cuenta_financiera`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_cuenta` | Requerido | ID de `cuentas_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `tipo` | Requerido | `cargo`, `abono`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `concepto` | Requerido | Texto; máximo 1000 caracteres. |
| `id_operacion` | Opcional, admite null | ID de `operaciones_financieras`. |
| `clave` | Requerido | Texto; máximo 100 caracteres. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### aplicacionescuenta

Modelo `AplicacionCuenta`; tabla `aplicaciones_cuenta_financiera`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_cargo` | Requerido | ID de `movimientos_cuenta_financiera`. |
| `id_abono` | Requerido | ID de `movimientos_cuenta_financiera`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### reportesmatriz

Modelo `ReporteMatriz`; tabla `reportes_matriz`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `corte_at` | Requerido | Fecha/hora. |
| `proveedor` | Requerido | Texto; máximo 100 caracteres. |
| `referencia` | Requerido | Texto; máximo 100 caracteres. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### detallesreporte

Modelo `DetalleReporteMatriz`; tabla `detalles_reporte_matriz`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_reporte` | Requerido | ID de `reportes_matriz`. |
| `servicio` | Requerido | `wu`, `biller`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### conciliaciones

Modelo `ConciliacionMatriz`; tabla `conciliaciones_matriz`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_arqueo_madre` | Requerido | ID de `arqueo_madre`. |
| `id_reporte` | Requerido | ID de `reportes_matriz`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### liquidaciones

Modelo `LiquidacionMatriz`; tabla `liquidaciones_matriz`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_arqueo_madre` | Requerido | ID de `arqueo_madre`. |
| `id_anterior` | Opcional, admite null | ID de `liquidaciones_matriz`. |
| `saldo_anterior_usd` | Opcional, admite null | Decimal, hasta 8 decimales. |
| `motivo_saldo_inicial` | Opcional, admite null | Texto; máximo 1000 caracteres. |
| `anticipo_usd` | Opcional | Decimal, hasta 8 decimales; no negativo; por defecto `0`. |
| `comision_anticipo_usd` | Opcional | Decimal, hasta 8 decimales; no negativo; por defecto `0`. |
| `saldo_matriz_nio` | Opcional, admite null | Decimal, hasta 8 decimales. |
| `saldo_interno_nio` | Opcional, admite null | Decimal, hasta 8 decimales. |
| `corte_comparacion_at` | Opcional, admite null | Fecha/hora. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### depositosliquidacion

Modelo `DepositoLiquidacion`; tabla `depositos_liquidacion`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_liquidacion` | Requerido | ID de `liquidaciones_matriz`. |
| `id_operacion` | Requerido | ID de `operaciones_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `fecha_acreditacion` | Opcional, admite null | Fecha `YYYY-MM-DD`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `id_cuenta_transito` | Opcional, admite null | ID de `cuentas_financieras`. |

### centroscosto

Modelo `CentroCosto`; tabla `centros_costo`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `codigo` | Requerido | Texto; máximo 60 caracteres. |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `estado` | Opcional | `activo`, `inactivo`; por defecto `activo`. |

### gastos

Modelo `GastoAdministrativo`; tabla `gastos_administrativos`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_cuenta` | Opcional, admite null | ID de CxP de la misma moneda; genera el cargo del gasto. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_desde` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_hasta` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_centro_costo` | Opcional, admite null | ID de `centros_costo`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_contraparte` | Opcional, admite null | ID de `contrapartes_financieras`. |
| `ambito` | Requerido | `administrativo`, `operativo`, `personal`. |
| `categoria` | Requerido | Texto; máximo 100 caracteres. |
| `concepto` | Requerido | Texto; máximo 1000 caracteres. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### obligacioneslaborales

Modelo `ObligacionLaboral`; tabla `obligaciones_laborales`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_operacion_anticipo` | Opcional, admite null | ID de `operaciones_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_desde` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_hasta` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_trabajador` | Requerido | ID de `trabajadores`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_cuenta` | Requerido | ID de `cuentas_financieras`. |
| `concepto` | Requerido | `salario`, `aguinaldo`, `vacaciones`, `indemnizacion`, `inss_laboral`, `inss_patronal`, `inatec`, `anticipo`, `descuento`, `otro`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### comisiones

Modelo `ComisionAdministrativa`; tabla `comisiones_administrativas`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_desde` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_hasta` | Requerido | Fecha `YYYY-MM-DD`. |
| `tipo` | Requerido | `proveedor`, `operador`, `bancaria`. |
| `naturaleza` | Requerido | `cobrar`, `pagar`. |
| `id_contraparte` | Opcional, admite null | ID de `contrapartes_financieras`. |
| `id_servicio` | Opcional, admite null | ID de `servicios_caja`. |
| `id_trabajador` | Opcional, admite null | ID de `trabajadores`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_cuenta` | Requerido | ID de `cuentas_financieras`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### proyectos

Modelo `ProyectoObra`; tabla `proyectos_obra`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `codigo` | Requerido | Texto; máximo 60 caracteres. |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_contraparte` | Opcional, admite null | ID de `contrapartes_financieras`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `estado` | Opcional | `activo`, `inactivo`; por defecto `activo`. |

### movimientosobra

Modelo `MovimientoObra`; tabla `movimientos_obra`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_proyecto` | Requerido | ID de `proyectos_obra`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `tipo` | Requerido | `costo`, `anticipo`, `recuperacion`, `devolucion`. |
| `partida` | Requerido | `material`, `mano_obra`, `servicio`, `otro`. |
| `concepto` | Requerido | Texto; máximo 1000 caracteres. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Opcional | Decimal, hasta 8 decimales; positivo. |
| `cantidad` | Opcional, admite null | Decimal, hasta 6 decimales; positivo. |
| `precio_unitario` | Opcional, admite null | Decimal, hasta 8 decimales; positivo. |
| `id_contraparte` | Opcional, admite null | ID de `contrapartes_financieras`. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### aplicacionesobra

Modelo `AplicacionAnticipoObra`; tabla `aplicaciones_anticipo_obra`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_anticipo` | Requerido | ID de `movimientos_obra`. |
| `id_costo` | Requerido | ID de `movimientos_obra`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### activos

Modelo `ActivoAdministrativo`; tabla `activos_administrativos`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `codigo` | Requerido | Texto; máximo 60 caracteres. |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `categoria` | Requerido | `terreno`, `edificio`, `equipo`, `vehiculo`, `otro`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_contraparte` | Opcional, admite null | ID de `contrapartes_financieras`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `cantidad` | Opcional | Decimal, hasta 6 decimales; positivo; por defecto `1`. |
| `precio_unitario` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### eventosactivos

Modelo `EventoActivo`; tabla `eventos_activo`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_activo` | Requerido | ID de `activos_administrativos`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `tipo` | Requerido | `traslado`, `baja`. |
| `cantidad` | Requerido | Decimal, hasta 6 decimales; positivo. |
| `id_subagencia_origen` | Opcional, admite null | ID de `subagencias`. |
| `id_subagencia_destino` | Opcional, admite null | ID de `subagencias`. |
| `concepto` | Requerido | Texto; máximo 1000 caracteres. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### financiamientos

Modelo `Financiamiento`; tabla `financiamientos`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `referencia` | Requerido | Texto; máximo 100 caracteres. |
| `id_cuenta` | Requerido | ID de `cuentas_financieras`. |
| `id_contraparte` | Requerido | ID de `contrapartes_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `principal` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `tasa_interes` | Opcional, admite null | Decimal, hasta 6 decimales; no negativo. |
| `base_tasa` | Opcional, admite null | `mensual`, `anual`, `otra`. |
| `periodicidad` | Requerido | `mensual`, `anual`, `otra`. |
| `numero_cuotas` | Opcional, admite null | Entero. |
| `fecha_vencimiento` | Opcional, admite null | Fecha `YYYY-MM-DD`. |
| `id_operacion_desembolso` | Opcional, admite null | ID de `operaciones_financieras`. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### cuotas

Modelo `CuotaFinanciamiento`; tabla `cuotas_financiamiento`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_financiamiento` | Requerido | ID de `financiamientos`. |
| `numero` | Requerido | Entero. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `capital` | Requerido | Decimal, hasta 8 decimales; no negativo. |
| `interes` | Opcional | Decimal, hasta 8 decimales; no negativo; por defecto `0`. |
| `seguro` | Opcional | Decimal, hasta 8 decimales; no negativo; por defecto `0`. |
| `comision` | Opcional | Decimal, hasta 8 decimales; no negativo; por defecto `0`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### incidencias

Modelo `IncidenciaFinanciera`; tabla `incidencias_financieras`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `referencia` | Requerido | Texto; máximo 100 caracteres. |
| `servicio` | Requerido | `biller`, `dhl`, `remesa`, `cambio`, `otro`. |
| `id_arqueo` | Opcional, admite null | ID de `arqueo`. |
| `id_envio_dhl` | Opcional, admite null | ID de `envios_dhl`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `id_operador` | Opcional, admite null | ID de `users`. |
| `biller` | Opcional, admite null | Texto; máximo 100 caracteres. |
| `referencia_cliente` | Opcional, admite null | Texto; máximo 255 caracteres. |
| `ticket` | Opcional, admite null | Texto; máximo 100 caracteres. |
| `caso` | Opcional, admite null | Texto; máximo 100 caracteres. |
| `motivo_incidencia` | Requerido | Texto; máximo 1000 caracteres. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `id_cuenta` | Opcional, admite null | ID de `cuentas_financieras`. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### gestionesincidencia

Modelo `GestionIncidencia`; tabla `gestiones_incidencia`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_incidencia` | Requerido | ID de `incidencias_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `gestion` | Requerido | `abierto`, `solicitado`, `confirmado`, `cerrado`. |
| `detalle` | Requerido | Texto; máximo 10000 caracteres. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |

### aplicaciones

Modelo `AplicacionFinanciera`; tabla `aplicaciones_financieras`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_operacion` | Requerido | ID de `operaciones_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `tasa_cambio` | Opcional, admite null | Decimal, hasta 6 decimales; positivo. |
| `id_gasto` | Opcional, admite null | ID de `gastos_administrativos`. |
| `id_obligacion` | Opcional, admite null | ID de `obligaciones_laborales`. |
| `id_comision` | Opcional, admite null | ID de `comisiones_administrativas`. |
| `id_obra` | Opcional, admite null | ID de `movimientos_obra`. |
| `id_activo` | Opcional, admite null | ID de `activos_administrativos`. |
| `id_cuota` | Opcional, admite null | ID de `cuotas_financiamiento`. |
| `id_financiamiento` | Opcional, admite null | ID de `financiamientos`. |
| `id_incidencia` | Opcional, admite null | ID de `incidencias_financieras`. |
| `componentes` | Opcional, admite null | Objeto/array; ver las reglas del proceso. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `id_cargo` | Opcional, admite null | ID de `movimientos_cuenta_financiera`. |

### cierres

Modelo `CierreFinanciero`; tabla `cierres_financieros`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `cuentas_capital_trabajo` | Opcional | Array no vacío de IDs distintos de bancos/POS seleccionados. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `periodo_desde` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_arqueo_madre` | Requerido | ID de `arqueo_madre`. |
| `id_liquidacion` | Requerido | ID de `liquidaciones_matriz`. |
| `motivo` | Requerido | Texto; máximo 10000 caracteres. |
| `confirmar_fuentes` | Requerido | `true`; confirmación explícita del administrador. |

### compensacioneslaborales

Modelo `CompensacionLaboral`; tabla `compensaciones_laborales`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_anticipo` | Opcional, admite null | ID de `obligaciones_laborales`. |
| `id_retencion` | Opcional, admite null | ID de obligacion de INSS laboral; excluyente con id_anticipo. |
| `id_obligacion` | Requerido | ID de `obligaciones_laborales`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

### historial

Modelo `HistorialFinanciero`; tabla `historial_financiero`.

Solo lectura. Devuelve entidad, id_registro, acción, antes/después, motivo, IP, autor y timestamps. Puede filtrarse por entidad e id_registro.

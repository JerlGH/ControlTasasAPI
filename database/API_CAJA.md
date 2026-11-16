# API de registros diarios de caja

La consolidacion administrativa, conciliacion con matriz, cuentas auxiliares y cierre financiero se documentan en [API_ADMINISTRACION.md](API_ADMINISTRACION.md). Son rutas nuevas bajo `/api/administracion`, para administradores; esta guia conserva el contrato individual de caja.

Todas las rutas descritas llevan el prefijo `/api` y requieren `Authorization: Bearer <token>` de Sanctum. Tambien se comprueba que el usuario este activo. Los ejemplos usan IDs ilustrativos de registros existentes; no crean ni actualizan usuarios, trabajadores, clientes, agencias o mesas de cambio.

Los importes se envian preferentemente como cadenas decimales. Las respuestas de los modelos mantienen la precision decimal mediante cadenas. `NIO` y `USD` son las monedas canonicas; las capturas con `C$`, `$`, `US$` y `U$` se normalizan. Las fechas de jornada se envian como `YYYY-MM-DD`. La hora y zona de los timestamps siguen la configuracion de Laravel.

## Recursos

| Ruta | Modelo | Controlador |
| --- | --- | --- |
| `/arqueos` | Arqueo | ArqueoController |
| `/cajas` | Caja | CajaController |
| `/detallearqueo` | Detalle_De_Arqueo | DetalleArqueoController |
| `/cheques` | Cheques | ChequesController |
| `/custodiascheques` | ArqueoCheque | ArqueoChequeController |
| `/pases` | Pases | PasesController |
| `/entradasysalidas` | Entradas_Y_Salidas | EntradasYSalidasController |
| `/tasascambioarqueo` | TasaCambioArqueo | TasaCambioArqueoController |
| `/operacionescambiocaja` | OperacionCambioCaja | OperacionCambioCajaController |
| `/remesasbancarias` | RemesaBancaria | RemesaBancariaController |
| `/precioproveedordolar` | Precio_Proveedor_Dolar | PrecioProveedorDolarController |
| `/enviosdhl` | EnvioDhl | EnvioDhlController |
| `/detallesenviosdhl` | DetalleEnvioDhl | DetalleEnvioDhlController |
| `/servicioscaja` | ServicioCaja | ServicioCajaController |
| `/conteosservicios` | ConteoServicioDiario | ConteoServicioDiarioController |
| `/enrolamientos` | Enrolamiento | EnrolamientoController |
| `/conteospapeleria` | ConteoPapeleria | ConteoPapeleriaController |
| `/historialcaja` | HistorialCaja | HistorialCajaController |

Cada recurso, excepto historial, ofrece GET de lista, GET `/{id}`, POST, PUT/PATCH `/{id}` y DELETE `/{id}`. Historial solo admite lecturas. Los campos `id_arqueo` o `id_envio_dhl` de origen son inmutables despues de crear el registro. DELETE exige un `motivo`; las operaciones con estado se anulan, y los conteos/configuraciones/desgloses se eliminan solamente cuando la jornada este abierta y las referencias lo permitan.

Las listas devuelven la coleccion en su clave de recurso y `Paginacion` con `total`, `pagina` y `por_pagina`. Admiten `page` y `per_page`, con maximo 100 filas. Los registros ligados a jornadas admiten `id_arqueo`, `id_usuario`, `id_subagencia`, `fecha_desde` y `fecha_hasta`. Tambien se pueden filtrar columnas disponibles del recurso como `moneda`, `tipo`, `categoria`, `estado`, `id_servicio`, `id_envio_dhl`, `momento`, `guia` y `numero_documento`. Los filtros nunca amplian los permisos del usuario.

Cajas, servicios y precios de proveedor requieren el rol `Administrador` para crear, modificar o retirar configuracion. Los demas usuarios consultan cajas y precios de su agencia (incluyendo precios globales), y el catalogo compartido de servicios. El codigo de un servicio usado no puede reasignarse a otro concepto. Los cajeros capturan solamente en sus jornadas abiertas. El administrador puede hacerlo en representacion del cajero; en pases el propietario siempre se obtiene del arqueo.

Las rutas existentes POST `/usuarios`, PUT `/usuarios/{id}` y DELETE `/usuarios/{id}` tambien exigen administrador. Esto protege la asignacion de roles utilizada por el modulo. No se alteran registros ni columnas de usuarios.

## Primera apertura

POST `/api/arqueos`:

```json
{
  "fecha": "2026-11-04",
  "id_caja": 1,
  "caja_inicial_cordobas": "10000.00",
  "caja_inicial_dolares": "200.00",
  "cheques_inicial_cordobas": "1000.00",
  "cheques_inicial_dolares": "0.00",
  "tasa_referencia": "36.700000",
  "motivo_apertura_manual": "Saldo de apertura verificado"
}
```

La agencia y el cajero se obtienen de la identidad autenticada si no se indican. El administrador puede indicar otro `id_usuario` y su agencia. El saldo inicial es el total incluyendo cheques. Una composicion desconocida puede dejarse en NULL; no debe convertirse automaticamente en cero.

Si hay un cierre previo del mismo cajero y agencia, POST con la nueva `fecha` toma sus totales contados, copia las referencias de cheques de cierre a la apertura y conserva caja y tasa de referencia cuando no se proporciona otra configuracion valida. Se puede indicar `id_arqueo_anterior`, pero debe ser el ultimo cierre del mismo cajero. No se crean nuevas operaciones de cheque ni se repiten sus ganancias. No puede haber otra jornada abierta ni abrirse una fecha anterior o igual a una ya registrada.

Una caja identificada solo puede tener una jornada abierta a la vez. Cambiar la tasa de referencia trasladada o corregirla en una jornada existente requiere administrador; los precios de compra/venta se capturan por separado.

Una apertura manual posterior utiliza `apertura_manual: true`, saldos y motivo, y requiere administrador. Una apertura trasladada no admite correcciones de saldo mediante PATCH. La primera apertura manual puede corregirse mientras este abierta, indicando de nuevo `motivo_apertura_manual`.

## Captura del dia

PATCH `/api/arqueos/1` informa los seis totales de plataforma y, si corresponde, la tasa de referencia. Los totales omitidos permanecen pendientes. Un cero es un valor informado:

```json
{
  "biller_cordobas": "1500.00",
  "biller_dolares": "-100.00",
  "envios_cordobas": "0.00",
  "envios_dolares": "0.00",
  "pagos_cordobas": "0.00",
  "pagos_dolares": "0.00"
}
```

Ejemplo de POST `/api/tasascambioarqueo`:

```json
{"id_arqueo": 1, "tipo": "Compra", "numero_precio": 1, "tasa": "36.500000"}
```

Ejemplo de POST `/api/operacionescambiocaja`:

```json
{"id_arqueo": 1, "id_tasa_arqueo": 1, "tipo": "Compra", "monto_dolares": "5.83", "tasa": "36.500000"}
```

Si se omite `monto_cordobas`, se calcula el producto exacto (`212.79500000` en el ejemplo). Se puede informar el importe aplicado en origen, sin imponer todavia un redondeo de pagos. La tasa seleccionada debe ser de la misma jornada y tipo; la tasa de una operacion nueva debe coincidir con ese precio. Cada operacion conserva su tasa aunque despues cambie la configuracion. `id_mesa_cambio` es opcional y unico: valida cajero, fecha, tipo, importes y tasa de la mesa existente y no la modifica.

Entradas/salidas exigen categoria `bancario`, `entre_agencias`, `gasto` o `varios`; tipo `Entrada`/`Salida`, moneda e importe positivo. `detalle` identifica el concepto de gasto o la contraparte declarada. Para un movimiento `mixto` se requieren `monto_efectivo` y `monto_cheques` cuya suma sea el total. Los pases usan `id_caja_contraparte` y los movimientos entre agencias `id_subagencia_contraparte`, cuando se conocen. Las referencias entre declaraciones requieren un administrador y deben coincidir en moneda, importe, sentido y contrapartes.

Las remesas bancarias conservan `banco`, `numero_remesa`, tipo, moneda y monto. No registrar el mismo hecho tambien como movimiento generico o envio de plataforma.

Los cheques guardan principal y ganancia por separado. `moneda_ganancia` puede diferir de `moneda`; si se omite utiliza la moneda del cheque. Puede conservarse principal NULL cuando la fuente solo informa ganancia. Para contarlo en custodia se requiere principal conocido. POST `/api/custodiascheques` usa `id_arqueo`, `id_cheque` y `momento` (`apertura` o `cierre`); moneda e importe se toman del cheque y cualquier valor proporcionado debe coincidir. Las custodias de apertura trasladadas son inmutables. En una primera apertura manual deben registrarse los documentos que explican su principal declarado.

## Conteo y cierre

POST `/api/detallearqueo` con las cantidades de piezas y estado `contado` calcula el total en el servidor. No se acepta `total` del cliente:

```json
{"id_arqueo": 1, "moneda": "USD", "cien": 2, "estado": "contado"}
```

Se requiere una fila por moneda, incluso si todas las cantidades son cero. USD no admite 200, 500 o 1000; NIO no usa la columna `dos` en el formato revisado. Las cantidades son enteros no negativos. Un conteo `borrador` conserva su total pendiente.

GET `/api/arqueos/1/resumen` muestra contado, esperado, diferencias, cheques, ganancias y datos pendientes. Si faltan totales no muestra un esperado o diferencia falsamente en cero. El esperado se calcula por moneda desde los valores de la jornada; el valor contado incluye efectivo y cheques de cierre. Una transferencia entre cuentas identificada como `medio: transferencia` no es un valor fisico en caja. DHL y enrolamientos no generan entradas monetarias.

POST `/api/arqueos/1/cerrar`:

```json
{"confirmar_cheques": true, "motivo": "Conteo diario verificado"}
```

El cierre valida los seis totales, los dos conteos, la composicion declarada de cheques de apertura y la confirmacion del inventario de cheques de cierre. Guarda los resultados, autor y fecha en una transaccion con el historial. No acepta importes finales o diferencias enviados por el cliente. Una diferencia no impide guardar el cierre, pero queda visible por moneda. Los datos financieros quedan bloqueados; no hay una reapertura generica.

## DHL, enrolamientos y conteos

Los dos formatos DHL se capturan en `/enviosdhl`, con desglose opcional en `/detallesenviosdhl`. Se permiten guias repetidas para verificarlas posteriormente. `tipo`, confirmacion y fechas originales conservan su texto. El formato abreviado puede informar `nombre_contacto` sin inventar remitente o beneficiario.

Despues del cierre, PATCH de un envio permite solo `estado_envio`, `confirmacion_original`, `fecha_entrega`, `fecha_entrega_original`, `observacion` y `motivo`. La entrega no puede preceder al envio. Guia, contenido y relaciones siguen bloqueados.

El seguimiento de un cheque cerrado permite estado de liquidacion, `fecha_liquidacion` y observacion, pero no anular/reactivar su operacion ni cambiar su principal o ganancia. Los snapshots de custodia anteriores permanecen iguales.

Enrolamientos registra persona, documento, fecha y codigo externo `usuario_servicio`. Puede enlazar un cliente existente si su documento corresponde; nunca crea ni modifica clientes. Un evento sin fecha normalizada puede conservar `fecha_original` y estado `borrador`. Para confirmar estado `registrado` se requiere fecha valida. Se permiten eventos repetidos de una persona.

Los conteos por servicio son independientes de las personas. Una fila con cantidad cero significa informado sin operaciones; la ausencia de fila significa no informado. No se deduce un ingreso de la cantidad. Papeleria admite concepto y cantidad sin fecha o jornada; si se proporciona arqueo, se toman su cajero, agencia y fecha.

## Extraccion e historial

Los archivos o procesos que verifican datos pueden consultar primero:

```text
GET /api/arqueos?fecha=2026-11-04&id_usuario=7
GET /api/arqueos/123/registros
GET /api/historialcaja?id_arqueo=123
```

La extraccion entrega el arqueo y todos sus registros relacionados, incluyendo estados `borrador` o `anulado` para que el verificador los distinga. El resumen monetario excluye operaciones anuladas. El historial conserva entidad, registro, autor, fecha, motivo y valores antes/despues. No tiene rutas de escritura o borrado.

Tambien se mantienen `/arqueos/ultimo/{id_usuario}`, `/arqueos/detalle/{id_arqueo}`, `/cheques/arqueo/{id_arqueo}`, `/pases/ultimos/{id_usuario}` y `/precioproveedordolar/ultimo`. Las consultas de listas son paginadas. El ultimo arqueo se ordena por fecha operativa; el ultimo precio excluye cotizaciones futuras e inactivas.

## Alcance y despliegue

Los errores de validacion usan HTTP 422; falta de autenticacion 401; falta de privilegio administrativo 403; registro no visible 404; conflictos de unicidad o dependencias 409. Las creaciones usan 201 y el resto de respuestas correctas 200.

Los contratos de captura se ampliaron: clientes antiguos deben enviar categoria, moneda y datos de apertura correspondientes, y utilizar la accion de cierre en lugar de escribir resultados derivados. No se han ejecutado migraciones sobre la base real. Si una definicion de expansion ya fue aplicada, necesita una migracion incremental antes de utilizar estos controladores. La consolidacion y los indicadores administrativos ya cuentan con API propia; la importacion automatica de los archivos sigue pendiente.

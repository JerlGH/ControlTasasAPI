# Servicios externos: terminal administrativa LAFISE / BANPRO

Actualización del 2026-11-06. Referencia modular incorporada íntegramente en [INTEGRACION_API_CSHARP_V4.md](INTEGRACION_API_CSHARP_V4.md). Para entregar el contrato completo al desarrollo C# basta V4; incluye campos, respuestas, clientes y compatibilidad con los módulos anteriores.

**Confirmaciones del 2026-11-06:** el usuario confirmó la tarifa BANPRO única para todas las transacciones de cuentas del día: 11 × C$5.50 = C$60.50. También confirmó movimientos sin comisión. Se añade `servicio=sin_comision`, con motivo obligatorio y ganancia cero explícita, separado de `sin_clasificar`. La [segunda revisión](REVISION_SERVICIOS_EXTERNOS.md) incorpora estas respuestas y su implementación.

La captura pertenece a **una terminal administrativa**. No agregar una captura nueva a cada cajero. Agencia y cajero son referencias del detalle. El responsable diario debe ser un administrador activo. Todos los administradores activos tienen acceso según el esquema de permisos vigente; `id_responsable` identifica responsabilidad y no concede permisos ni establece exclusividad entre administradores.

## 1. URL, acceso y formatos

- Base: `{servidor}/api/administracion/`. Se conservan las rutas anteriores; no hay prefijo `/v3`.
- Todas las rutas requieren `Authorization: Bearer {token}`, Sanctum y administrador activo, incluidos GET. Enviar `Accept: application/json` y `Content-Type: application/json`.
- Banco: exactamente `lafise` o `banpro`. Moneda: `NIO` o `USD`. Dirección: `Entrada` o `Salida`, desde central/POS, inversa al cajero.
- Fechas operativas: `yyyy-MM-dd`. No usar la fecha de creación informática para agrupar movimientos. El cliente suministra el día explícitamente.
- Importes: cadenas con punto, hasta 16 enteros y ocho decimales; deben ser positivos. `monto_remesa_usd` admite dos decimales. Conservar referencias como texto.
- Las ganancias, tarifas y totales monetarios se devuelven como cadenas; las ganancias definitivas pueden ser `null` en un resumen incompleto. No deserializar dinero en `double`.
- Cada fila representa **una transacción**, sin campo multiplicador `cantidad` editable.

## 2. Rutas nuevas

Se añaden 18 combinaciones método/ruta, sin contar HEAD. PUT y PATCH tienen la misma semántica de actualización parcial.

| Método | Ruta relativa | Respuesta HTTP / propiedad |
| --- | --- | --- |
| GET | `serviciosexternos/catalogo` | 200, `Resultado`: tarifario actual. |
| GET | `serviciosexternos/resumen?fecha_desde=2026-11-01&fecha_hasta=2026-11-30` | 200, `Resultado`: suma de días cerrados. |
| GET | `arqueosserviciosexternos` | 200, `Registros` + `Paginacion`. |
| GET | `arqueosserviciosexternos/{id}` | 200, `Registro`. |
| POST | `arqueosserviciosexternos` | 201, `Registro`. |
| PUT / PATCH | `arqueosserviciosexternos/{id}` | 200, `Registro`. |
| DELETE | `arqueosserviciosexternos/{id}` | 200, `Registro` anulado. |
| GET | `transaccionesexternas` | 200, `Registros` + `Paginacion`. |
| GET | `transaccionesexternas/{id}` | 200, `Registro`. |
| POST | `transaccionesexternas` | 201, `Registro`. |
| PUT / PATCH | `transaccionesexternas/{id}` | 200, `Registro`. |
| DELETE | `transaccionesexternas/{id}` | 200, `Registro` anulado. |
| GET | `arqueosserviciosexternos/{id}/resumen` | 200, `Resultado`: cálculo o snapshot cerrado. |
| POST | `arqueosserviciosexternos/{id}/cerrar` | 200, `Resultado`: cálculo congelado. |
| POST | `arqueosserviciosexternos/{id}/generarreporte` | 200, **`Registro`**: reporte central confirmado. |
| POST | `arqueosserviciosexternos/{id}/registrarcomisiones` | 200, **`Resultado.comisiones`**: documentos financieros creados o recuperados. |

Las respuestas incluyen `Status` numérico. Un ejemplo de lista vacía:

```json
{"Registros":[],"Paginacion":{"total":0,"pagina":1,"por_pagina":50},"Status":200}
```

Los GET de detalle no incluyen automáticamente relaciones. Recuperar las filas con `transaccionesexternas?id_arqueo_externo={id}`; recuperar el reporte generado con `reportesremesascentrales?id_arqueo_externo={id}` y las comisiones con `comisiones?id_arqueo_externo={id}`.

## 3. Pantalla administrativa propuesta

Una opción de menú «Servicios externos» abre un calendario o listado diario de la terminal principal. La pantalla del día muestra responsable, agencias incluidas, tabla de transacciones, contadores, principal por moneda y ganancia en NIO. Permite filtrar por banco, servicio, agencia y dirección. Incluye la opción «Sin comisión» para movimientos confirmados como no remunerados y exige su motivo. Mostrar por separado cantidades con comisión, sin comisión y pendientes de clasificar.

Acciones de borrador: agregar, editar, clasificar y anular filas; consultar resumen; revisar pendientes; cerrar. Acciones posteriores al cierre: consultar, emitir reporte central y reconocer comisiones. Mostrar por separado ganancia calculada, comisión reconocida y cobrada. Un cierre externo no implica cobro del proveedor.

No replicar la pantalla dentro de cada caja. Un selector de agencia filtra referencias del detalle y no cambia el contador de la terminal ni la tarifa BANPRO del día. No recalcular la ganancia con las filas visibles de la cuadrícula o de una sola página de resultados.

## 4. Abrir el registro del día

POST `arqueosserviciosexternos`:

```json
{
  "fecha":"2026-11-15",
  "agencias_esperadas":[1,2],
  "id_responsable":8,
  "id_documento":null,
  "observacion":"Captura de la terminal principal"
}
```

| Campo de captura | Regla |
| --- | --- |
| `fecha` | Obligatorio; fecha operativa inmutable. Debe pertenecer a un periodo financiero abierto. |
| `agencias_esperadas` | Lista obligatoria, no vacía, de IDs distintos y existentes de subagencias activas. Define la cobertura del reporte, incluidos sus ceros. |
| `id_responsable` | Opcional o null; por defecto el administrador autenticado. Si se declara, debe ser administrador activo. |
| `id_documento` | Opcional o null; documento financiero vigente, solo metadata ya registrada. |
| `observacion` | Opcional o null; máximo 10,000 caracteres. |
| `motivo` | Opcional para auditoría; máximo 10,000. No se guarda como columna del registro. |

Solo puede existir un registro vigente por día para `terminal="principal"`. Un POST repetido produce 409; consultar el listado por fecha antes de intentar abrir otro.

La respuesta de cabecera contiene además `id`, `terminal`, `clave_activa`, `tarifario` JSON, `resumen` JSON o null, `estado`, `cerrado_at` o null, `id_usuario_registro`, `created_at` y `updated_at`. Esos campos son calculados o asignados por el servidor y no se envían en POST/PATCH. Los IDs son enteros.

`estado`: `borrador`, `cerrado`, `anulado`. El tarifario actual es `2026-11-06.v2`, que añade el movimiento sin comisión con tarifa cero a ambos bancos. Las tarifas remuneradas no cambian. La cabecera conserva su copia; GET catálogo devuelve el tarifario actual y no sustituye al snapshot histórico. Al capturar el primer `sin_comision` en un borrador V1, el servidor habilita esa clase y registra `habilitar_sin_comision` en auditoría, conservando las tarifas anteriores. Los cierres V1 permanecen intactos.

PATCH permite cambiar responsable, cobertura, documento y observación en borrador. No puede excluirse una agencia con filas vigentes. La fecha es inmutable. Un registro cerrado no admite cambios de captura, reapertura ni anulación.

## 5. Capturar y clasificar transacciones

POST `transaccionesexternas`, ejemplo LAFISE:

```json
{
  "id_arqueo_externo":12,
  "clave":"9b42df937f3e456da095e333acfb5e31a",
  "id_subagencia":1,
  "id_cajero":null,
  "nombre_cajero":"Referencia del comprobante",
  "banco":"lafise",
  "servicio":"remesa",
  "tipo":"Entrada",
  "moneda":"USD",
  "numero_referencia":"00001234-ABC",
  "monto":"250.00",
  "id_documento":null
}
```

BANPRO, extranjera pagada en NIO:

```json
{
  "id_arqueo_externo":12,
  "clave":"7d5dd737e9934e88ae4f7a1aec3e971e6",
  "id_subagencia":1,
  "nombre_cajero":"Referencia del comprobante",
  "banco":"banpro",
  "servicio":"remesa_extranjera",
  "tipo":"Entrada",
  "moneda":"NIO",
  "numero_referencia":"REM-00017",
  "monto":"3674.00",
  "monto_remesa_usd":"100.00"
}
```

La segunda fila genera C$24, no USD24 ni un porcentaje de C$3,674. El importe original USD se captura del comprobante. No se deriva dividiendo el pago por la tasa del arqueo.

Movimiento confirmado sin comisión, usando la misma ruta:

```json
{
  "id_arqueo_externo":12,
  "clave":"f643283fe2bd4c3db387f46d1763701b7",
  "id_subagencia":1,
  "nombre_cajero":"Referencia del comprobante",
  "banco":"banpro",
  "servicio":"sin_comision",
  "motivo_sin_comision":"Fondeo del POS sin comision",
  "tipo":"Entrada",
  "moneda":"NIO",
  "numero_referencia":"FONDEO-001",
  "monto":"5000.00"
}
```

Su principal de C$5,000 se conserva en los totales y en el reporte central. Su ganancia es C$0 y no incrementa el contador del tramo BANPRO. La clasificación es una declaración administrativa explícita: la API no la deduce del texto de la referencia.

| Campo | Regla |
| --- | --- |
| `id_arqueo_externo` | Obligatorio, existente y en borrador; inmutable en PATCH. |
| `clave` | Obligatoria, texto de hasta 100, única dentro del registro diario, incluso después de anular la fila. Generar una vez por intento lógico, por ejemplo GUID sin guiones. Inmutable. |
| `id_subagencia` | Obligatorio; pertenece a `agencias_esperadas` y está activa. |
| `id_cajero` | Opcional o null; si se indica, usuario activo perteneciente a la agencia. No se cambia su rol ni se le exige una jornada nueva. |
| `nombre_cajero` | Opcional si hay `id_cajero`; de otro modo obligatorio y no vacío. Máximo 255. Referencia del comprobante. |
| `banco` | Obligatorio: `lafise`, `banpro`. |
| `servicio` | Obligatorio; valores de la siguiente tabla o `sin_clasificar`. |
| `motivo_sin_comision` | Obligatorio y no vacío para `servicio=sin_comision`; máximo 10,000 caracteres. Se guarda sin espacios al principio/final. Para otras clases, omitir o enviar null; enviar null al reclasificar un movimiento que tenía motivo. No sustituirlo por `motivo`, que es de auditoría. |
| `tipo` | Obligatorio: `Entrada`, `Salida`, desde central/POS. |
| `moneda` | Obligatoria: `NIO`, `USD`; moneda del principal registrado o pagado. |
| `numero_referencia` | Obligatorio, texto de hasta 100. Puede repetirse en filas diferentes. |
| `monto` | Obligatorio, positivo, hasta 16 enteros y ocho decimales. Es principal, no ganancia. |
| `monto_remesa_usd` | Opcional en borrador; necesario al cerrar una extranjera BANPRO pagada en NIO. Positivo, dos decimales, USD0.01–1500.00. Para otras clases debe omitirse o enviarse null. |
| `id_remesa_caja` | Opcional o null; enlace de contraste, sujeto a las reglas del apartado 8. |
| `id_documento` | Opcional o null; documento vigente. |
| `observacion`, `motivo` | Opcionales o null; máximo 10,000 cada uno. Motivo se utiliza en auditoría. |

| Banco | `servicio` | Ganancia NIO |
| --- | --- | --- |
| lafise | `transaccion_cuenta` | 7 por fila. |
| lafise | `remesa` | 20 por fila. |
| banpro | `transaccion_cuenta` | Según total diario de la terminal. |
| banpro | `recepcion_empresa` | 5 por fila. |
| banpro | `servicio_publico` | 4 por fila. |
| banpro | `remesa_local` | 12 por fila. |
| banpro | `remesa_extranjera` | 13 si el pago es USD; si es NIO, depende de la base original USD. |
| lafise / banpro | `sin_comision` | 0 confirmado, con motivo obligatorio. |

`sin_clasificar` permite una captura provisional de cualquier banco. Debe corregirse antes del cierre. No equiparar automáticamente cualquier `Entrada` a una remesa ni cualquier `Salida` a una transacción de cuenta: la dirección no identifica el servicio.

`sin_comision` es una clasificación definitiva válida para cerrar. Conserva el movimiento monetario y exige justificación. No es una anulación ni un servicio desconocido. Un día que solo tiene movimientos de esta clase puede cerrar y emitir su reporte; no necesita generar comisiones por cobrar de monto cero. El importe del movimiento sigue siendo positivo. La captura de servicios sin movimiento monetario no está incluida en esta ampliación.

Las tarifas de cuentas BANPRO se aplican a **todas** las filas `banco=banpro, servicio=transaccion_cuenta` del día de la única terminal:

| Cantidad diaria | Tarifa NIO por fila | Ejemplo de total |
| --- | --- | --- |
| 1–10 | 5 | 10 → 50 |
| 11–20 | 5.50 | 11 → 60.50; 20 → 110 |
| 21–30 | 6 | 21 → 126; 30 → 180 |
| 31 o más | 7 | 31 → 217 |

Se cuentan ambas direcciones, monedas y todas las agencias/cajeros referenciados. No es un cálculo progresivo. Las remesas, empresas, servicios públicos y movimientos `sin_comision` se excluyen del contador de cuentas. Diez transacciones de cuentas más dos fondeos sin comisión siguen generando C$50; la tarifa sube cuando se registra la undécima transacción de cuentas.

Para extranjeras BANPRO pagadas en NIO:

| Base original USD, extremos incluidos | Tarifa NIO |
| --- | --- |
| 0.01–29.99 | 13 |
| 30.00–100.99 | 24 |
| 101.00–200.99 | 28 |
| 201.00–400.99 | 33 |
| 401.00–1500.00 | 38 |

No hay tarifa autorizada por encima de USD1500 para pago NIO: se rechaza. Para pago USD se usa C$13 por fila, sin convertir esos C$13 a USD y sin trasladarle el límite del pago NIO.

PATCH puede corregir los datos de una fila mientras el padre esté en borrador, conservando `clave` y `id_arqueo_externo`. Para cambiar de extranjera NIO a otro servicio o moneda, enviar también `monto_remesa_usd:null` si existía una base guardada.

La respuesta contiene los campos anteriores y `id`, `estado`, `clave_remesa_activa`, `tarifa_nio`, `ganancia_nio`, `calculo`, `id_usuario_registro` y timestamps. En borrador los tres campos de cálculo guardados son null: la vista previa actual se obtiene del resumen, donde todas las filas se recalculan juntas. Al cerrar se guardan definitivamente. No enviar el objeto de respuesta completo como PATCH.

## 6. Resumen y cierre

GET `arqueosserviciosexternos/{id}/resumen` devuelve:

| Propiedad de `Resultado` | Contenido |
| --- | --- |
| `id_arqueo_externo`, `fecha`, `terminal`, `id_responsable`, `agencias_esperadas` | Identidad del registro y cobertura. |
| `tarifario` | Copia completa de reglas y versión utilizadas. |
| `transacciones_cuenta_banpro` | Contador entero de filas de cuentas BANPRO de la terminal. |
| `tarifa_transacciones_banpro_nio` | Tarifa por fila; cero cuando no hay transacciones de cuentas. |
| `totales` | Cantidades, principales por moneda y ganancias del día. |
| `por_banco` | Objeto con claves `lafise` y `banpro`, siempre presentes. |
| `por_agencia_banco` | Lista con las dos combinaciones por agencia, incluidas las de cero movimiento. |
| `por_servicio` | Lista de combinaciones banco/servicio presentes. Vacía si no hay filas. |
| `lineas_reporte` | Ocho combinaciones por agencia con monto y cantidad, desde central/POS. |
| `transacciones` | Lista completa de filas vigentes con cálculo actualizado. No está paginada. |
| `pendientes` | Lista `{id_transaccion,errores}`, con mensajes por campo. ID null indica un problema de cabecera, como responsable o agencia que dejó de estar activo. |
| `completo` | Booleano, true solo cuando no hay pendientes. |
| `huella` | SHA-256 opaco del resumen utilizado para cerrar. |

Cada bloque de totales tiene esta estructura; ejemplo vacío:

```json
{
  "cantidad":0,
  "cantidad_con_comision":0,
  "cantidad_sin_comision":0,
  "entradas":{"NIO":"0.00000000","USD":"0.00000000"},
  "salidas":{"NIO":"0.00000000","USD":"0.00000000"},
  "neto":{"NIO":"0.00000000","USD":"0.00000000"},
  "cantidades":{"NIO":{"Entrada":0,"Salida":0},"USD":{"Entrada":0,"Salida":0}},
  "ganancia_nio":"0.00000000",
  "ganancia_calculada_nio":"0.00000000",
  "pendientes":0
}
```

`neto=entradas−salidas` expresa principal. `ganancia_nio` es comisión estimada, independiente de ese neto. Si una fila está pendiente, `ganancia_nio` del bloque afectado es null; `ganancia_calculada_nio` conserva el subtotal provisional de filas calculables. Ese subtotal no reemplaza la ganancia definitiva.

`cantidad` incluye todos los movimientos vigentes. `cantidad_con_comision` cuenta los calculables remunerados; `cantidad_sin_comision`, los confirmados con esa clasificación. Las filas todavía pendientes no forman parte de estos dos últimos contadores. La separación aparece en los totales del día, por banco, por agencia/banco y por servicio. En los reportes centrales se conserva el conteo de todos los movimientos monetarios.

El cálculo por fila incorpora `calculo.version`, `servicio`, `moneda_ganancia`, `cantidad=1`, `tarifa_nio`, `base_usd` o null, `motivo_sin_comision` o null y `cantidad_terminal_dia` o null. Para cuentas BANPRO este último explica la tarifa común; para extranjeras, `base_usd` identifica el importe original aplicable. Un cero confirmado guarda tarifa y ganancia `"0.00000000"` y su motivo; un servicio sin clasificar conserva resultado null.

POST `arqueosserviciosexternos/{id}/cerrar`:

```json
{
  "confirmar_registros":true,
  "huella":"0000000000000000000000000000000000000000000000000000000000000000",
  "motivo":"Captura y clasificacion revisadas"
}
```

La huella de ceros es ilustrativa y se rechazará: copiar la obtenida al mostrar el resumen real al usuario. Si otro administrador cambió la captura, se recibe 422 en `huella`. Volver a consultar y solicitar la revisión de los nuevos datos; no actualizar la huella y cerrar automáticamente.

El cierre exige servicios clasificados, bases USD necesarias, referencias vigentes y fecha financiera abierta. Congela el resumen y copia tarifa/ganancia/cálculo a cada fila. El GET posterior devuelve exactamente el snapshot, sin aplicar tarifas nuevas. Un día sin movimientos puede cerrarse con confirmación explícita y sus ceros declarados.

## 7. Emitir reporte central para Libro Diario

Después del cierre, POST `arqueosserviciosexternos/{id}/generarreporte`:

```json
{"corte_at":"2026-11-15 18:00:00"}
```

Crea un `ReporteRemesaCentral` confirmado, con referencia `TERMINAL-{id}`, la procedencia `id_arqueo_externo` y ocho detalles por agencia. No duplica el reporte si se repite con el mismo corte; un corte diferente produce 422. La fecha del corte debe coincidir con el registro.

`corte_at` exige exactamente `yyyy-MM-dd HH:mm:ss`, como hora local operativa sin fracciones, `Z` ni offset. Esta precisión coincide con la que conserva el reporte y evita reintentos inconsistentes por pérdida de fracciones. El corte identifica el reporte de **todo el día**; no filtra por hora las transacciones, que no tienen hora de ejecución capturada. Cada total a emitir debe caber en 16 enteros y ocho decimales; si la suma lo supera, se rechaza la emisión con 422 en `reporte`, sin dejar documentos parciales.

La respuesta es **`Registro`**, no `Resultado`. En el cliente administrativo de V4 usar `EjecutarAccionRegistroAsync`. No volver a confirmarlo mediante el endpoint antiguo.

Puede vincularse al borrador de Libro Diario mediante el campo ya existente `id_reporte_remesas`. Deben coincidir fecha y conjunto de agencias del Diario. Si el nuevo registro solo cubre una parte de las agencias, no reemplaza por sí solo un reporte global de cobertura mayor. El Diario continúa comparando cifras independientes y direcciones opuestas a los cajeros.

Emitir el reporte antes del cierre financiero del periodo; generar un documento nuevo en una fecha financiera ya cerrada no está permitido. Consultar o recuperar un reporte ya emitido no modifica el pasado.

## 8. Enlace opcional con una remesa de caja

`id_remesa_caja` no obliga a los cajeros a usar la nueva pantalla. Es una referencia de contraste a `remesas_bancarias`, con una remesa de origen vigente y jornada cerrada.

Exige misma fecha operativa, agencia, banco, moneda, importe y número de remesa, y dirección opuesta. Si también se indica `id_cajero`, debe corresponder al propietario de esa jornada. Cada remesa solo puede estar enlazada a una fila externa vigente. Anular la fila en borrador libera ese enlace.

No crea ni modifica una remesa de caja, una operación financiera o un saldo POS. El registro central se captura de su fuente y se contrasta; no se copia automáticamente la caja para fabricar un resultado coincidente.

## 9. Reconocer y cobrar las ganancias

Cerrar el día congela la estimación de ganancias. Para registrar la cuenta por cobrar al proveedor, POST `arqueosserviciosexternos/{id}/registrarcomisiones`:

```json
{
  "fecha":"2026-11-15",
  "cuentas":[
    {"banco":"lafise","id_cuenta":51},
    {"banco":"banpro","id_cuenta":52}
  ]
}
```

Declarar uno o dos proveedores, sin repetir banco ni cuenta. Cada cuenta debe estar activa, ser `por_cobrar`, moneda NIO, **`rubro_capital=pendiente`**, tener contraparte vigente y no pertenecer a una agencia individual. Una cuenta ya usada por una comisión externa vigente de LAFISE no puede usarse para BANPRO, ni a la inversa, incluso mediante solicitudes o días distintos. La fecha no puede preceder al servicio y debe estar abierta cuando se crea el devengo. La cuenta debe existir desde esa fecha. Elegir cuentas de los proveedores correctos; el API valida las relaciones y naturaleza contable, no deduce el banco a partir del nombre libre de la contraparte.

El monto proviene del cierre; no se recibe en el body. No reconocer un proveedor sin ganancias. Crea una comisión `tipo=proveedor`, `naturaleza=cobrar`, periodo de un día, y el cargo auxiliar que ya genera el módulo de comisiones. **No registra efectivo recibido.**

La respuesta es `Resultado.comisiones`, lista de los documentos generados/recuperados. Si ya existe la comisión vigente con la misma fecha y cuenta, se devuelve sin volver a generar cargo. Si se intenta cambiar esas condiciones se recibe 422.

Los documentos pueden consultarse con `comisiones?id_arqueo_externo={id}&banco_externo=lafise&estado=registrado`. No crear además una comisión manual por el mismo servicio.

El cobro utiliza la operación real de ingreso y una aplicación al `id_comision`, conservando el proceso anterior incorporado en V4. Ejemplo de aplicación, una vez registrada la operación real:

```json
{"fecha":"2026-11-20","id_operacion":301,"monto":"50.00","id_comision":81}
```

POST `aplicaciones` devuelve `Registro`. Admite cobros parciales y controla saldo pendiente. No volver a registrar el principal de las transacciones como si fuera cobro de comisión.

Una comisión sin aplicaciones puede anularse mediante DELETE `comisiones/{id}` con motivo, respetando cierres y dependencias. Eso anula su cargo y libera `clave_externa_activa`; la acción de reconocimiento puede reemitirla con la fecha/cuenta correctas en periodo abierto. Las comisiones anteriores conservan `id_arqueo_externo` y estado `anulado`. Una comisión aplicada debe resolver primero las aplicaciones conforme al flujo existente. No hay edición directa del monto contabilizado.

## 10. Cambios que afectan contratos anteriores

| Respuesta previa | Adición |
| --- | --- |
| `ReporteRemesaCentral` | `id_arqueo_externo`: entero nullable, solo lectura. Nuevo filtro GET de igualdad con ese nombre. |
| `ComisionAdministrativa` | `id_arqueo_externo`: entero nullable; `banco_externo`: texto nullable; `clave_externa_activa`: texto nullable. Solo lectura en captura. Nuevos filtros por origen y banco. |
| Capital: `fuentes` | `servicios_externos`: lista de orígenes usados, con día, estado, huella y comisiones reconocidas. |
| Capital: `pendientes` | Nuevos tipos `servicios_externos_sin_cerrar`, `comisiones_externas_sin_reconocer` y `comision_externa_inconsistente`. |
| Cierre financiero | Rechaza el cierre mientras haya borradores de la terminal o ganancias positivas de días hasta el corte sin comisión vigente reconocida hasta ese corte. |

Las ganancias de la terminal se incorporan al capital mediante la cuenta por cobrar generada. No se vuelve a sumar el snapshot como ingreso adicional. Los cobros de comisiones se tratan con la fórmula de capital ya existente.

Un reconocimiento posterior a la fecha de corte no satisface el devengo del corte anterior. Un proveedor con ganancia cero no exige comisión. Los registros anulados se excluyen. Si no existe ningún registro de este nuevo módulo, la API no inventa jornadas ni exige retroactivamente un historial de servicios no capturado.

`comision_externa_inconsistente` incluye `id_arqueo_externo`, `banco`, `id_comision` e `id_cuenta`. Detecta una comisión previa cuyo monto, naturaleza, moneda o cuenta no corresponden al origen y a su inclusión en pendientes del capital, o una cuenta que mezcla comisiones vigentes de los dos proveedores hasta ese corte. No da por resuelto el devengo solo por encontrar un documento con el mismo origen. Un cambio de la cuenta a inactiva no borra por sí solo una deuda histórica correctamente contabilizada.

El tarifario de cada día y el detalle financiero se conservan separados: una ganancia estimada no equivale al saldo pendiente ni al monto cobrado. Para pendientes/cobros consultar el estado de cuenta y las aplicaciones documentadas en V4.

## 11. Listados, filtros y anulación

Los dos listados admiten `page` desde 1, `per_page` entre 1 y 100, `fecha_desde` y `fecha_hasta` inclusivas, orden por ID descendente. Por defecto son 50 filas. Las fechas filtran la jornada operativa, también en las transacciones.

- Cabeceras: igualdad en `estado`, `id_responsable`.
- Transacciones: igualdad en `id_arqueo_externo`, `id_subagencia`, `id_cajero`, `banco`, `servicio`, `tipo`, `moneda`, `estado`, `numero_referencia`, `clave`.

Los campos desconocidos se rechazan con 422 en los nuevos endpoints; no utilizar filtros inexistentes. Los listados de recursos antiguos conservan sus demás reglas.

DELETE requiere body JSON:

```json
{"motivo":"Referencia capturada incorrectamente; se conserva la fila anulada"}
```

La anulación conserva datos y auditoría. Anular un borrador de cabecera anula sus filas y libera la fecha para un nuevo registro. La clave de una fila anulada no se reutiliza dentro de la misma cabecera. Los cerrados conservan la evidencia; no tienen endpoint de reapertura.

Si un POST de fila pierde su respuesta, consultar `transaccionesexternas?id_arqueo_externo={id}&clave={clave_original}`. Reenviar la misma clave produce 409 y evita una segunda fila. No generar un GUID nuevo automáticamente ante un timeout.

## 12. Consulta por periodo

GET `serviciosexternos/resumen` exige ambas fechas; máximo 366 días por solicitud. Devuelve `Resultado` con `fecha_desde`, `fecha_hasta`, `moneda_ganancia=NIO`, `cantidad` total, `cantidad_con_comision`, `cantidad_sin_comision`, `ganancia_nio`, `por_banco` y las siguientes listas:

- `dias`: `{id,fecha,totales,por_banco}` de cada registro cerrado.
- `borradores`: `{id,fecha}` aún sin cerrar, excluidos de la suma definitiva.
- `fechas_sin_registro`: lista de fechas `yyyy-MM-dd` del intervalo que no tienen una cabecera vigente. Una cabecera anulada no satisface la cobertura. No incluye las fechas que tienen borrador.

Suma los resultados congelados de cada día. No reúne transacciones de distintos días para asignar un nuevo rango. No convierte una fecha sin cabecera en un día confirmado con cero movimientos. Los días anulados se excluyen.

Los contadores con/sin comisión también aparecen en `por_banco` del periodo. En los cierres V1 no existía `sin_comision`: el total anterior se interpreta como cantidad con comisión y la cantidad sin comisión como cero. Se conserva el JSON de cada día V1; si el formulario lo muestra directamente, tratar los contadores nuevos ausentes con esa misma equivalencia.

La nueva lista permite a C# mostrar por separado «cerrado sin movimientos», «borrador» y «sin registro». No se conocen todavía el inicio obligatorio de este control ni el calendario laboral de la terminal: una fecha ausente se informa y no se convierte automáticamente en una falta que impida el cierre financiero.

## 13. Cliente C# usando el cliente administrativo de V4

El siguiente bloque utiliza `ApiAdministracionClient` del apartado 7 de V4, conserva el `HttpClient` autenticado y no inicia una sesión nueva. `JsonElement` conserva las cadenas y estructuras completas de los nuevos resultados; puede sustituirse por los DTOs tipados de V4. No reutilizar un DTO de captura como payload del objeto de respuesta.

```csharp
using System;
using System.Collections.Generic;
using System.Globalization;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace ControlCaja.Integracion
{
    public sealed class ApiServiciosExternosClient
    {
        private readonly ApiAdministracionClient api;
        public ApiServiciosExternosClient(ApiAdministracionClient cliente) =>
            api = cliente ?? throw new ArgumentNullException(nameof(cliente));

        public Task<JsonElement> AbrirAsync(string fecha, long[] agencias, long? responsable = null,
            CancellationToken ct = default) =>
            api.CrearAsync<JsonElement>("arqueosserviciosexternos",
                new { fecha, agencias_esperadas = agencias, id_responsable = responsable }, ct);

        // Generar clave una sola vez y conservarla en el formulario hasta conocer el resultado.
        public Task<JsonElement> CapturarAsync(long idDia, string clave, long agencia, string cajero,
            string banco, string servicio, string tipo, string moneda, string referencia, decimal principal,
            decimal? baseUsd = null, CancellationToken ct = default)
        {
            ValidarPrincipal(principal);
            if (baseUsd.HasValue && (baseUsd.Value < 0.01m || baseUsd.Value > 1500m || decimal.Round(baseUsd.Value, 2) != baseUsd.Value))
                throw new ArgumentOutOfRangeException(nameof(baseUsd), "Copiar el importe USD del comprobante con dos decimales, sin redondearlo.");
            return api.CrearAsync<JsonElement>("transaccionesexternas", new
            {
                id_arqueo_externo = idDia, clave, id_subagencia = agencia, nombre_cajero = cajero,
                banco, servicio, tipo, moneda, numero_referencia = referencia,
                monto = principal.ToString("0.########", CultureInfo.InvariantCulture),
                monto_remesa_usd = baseUsd?.ToString("0.00", CultureInfo.InvariantCulture)
            }, ct);
        }

        public Task<JsonElement> CapturarSinComisionAsync(long idDia, string clave, long agencia, string cajero,
            string banco, string tipo, string moneda, string referencia, decimal principal, string motivo,
            CancellationToken ct = default)
        {
            ValidarPrincipal(principal);
            if (string.IsNullOrWhiteSpace(motivo) || motivo.Length > 10000)
                throw new ArgumentException("Indicar el motivo del movimiento sin comision.", nameof(motivo));
            return api.CrearAsync<JsonElement>("transaccionesexternas", new
            {
                id_arqueo_externo = idDia, clave, id_subagencia = agencia, nombre_cajero = cajero,
                banco, servicio = "sin_comision", motivo_sin_comision = motivo.Trim(), tipo, moneda,
                numero_referencia = referencia, monto = principal.ToString("0.########", CultureInfo.InvariantCulture)
            }, ct);
        }

        private static void ValidarPrincipal(decimal principal)
        {
            if (principal <= 0 || principal > 9999999999999999.99999999m || decimal.Round(principal, 8) != principal)
                throw new ArgumentOutOfRangeException(nameof(principal), "El importe admite 16 enteros y ocho decimales.");
        }

        public Task<JsonElement> ResumenAsync(long id, CancellationToken ct = default) =>
            api.ConsultarResultadoAsync<JsonElement>($"arqueosserviciosexternos/{id}/resumen", null, ct);

        // Pasar la huella que vio el usuario. No refrescarla y aprobarla automaticamente.
        public Task<JsonElement> CerrarAsync(long id, string huellaRevisada, CancellationToken ct = default) =>
            api.EjecutarAccionAsync<JsonElement>("arqueosserviciosexternos", id, "cerrar",
                new { confirmar_registros = true, huella = huellaRevisada }, ct);

        // Esta accion utiliza Registro, igual que confirmar el reporte central en V3.
        public Task<JsonElement> EmitirReporteAsync(long id, string corteAt, CancellationToken ct = default) =>
            api.EjecutarAccionRegistroAsync<JsonElement>("arqueosserviciosexternos", id, "generarreporte",
                new { corte_at = corteAt }, ct);

        public Task<JsonElement> ReconocerAsync(long id, string fecha, long? cuentaLafise,
            long? cuentaBanpro, CancellationToken ct = default)
        {
            var cuentas = new List<object>();
            if (cuentaLafise.HasValue) cuentas.Add(new { banco = "lafise", id_cuenta = cuentaLafise.Value });
            if (cuentaBanpro.HasValue) cuentas.Add(new { banco = "banpro", id_cuenta = cuentaBanpro.Value });
            return api.EjecutarAccionAsync<JsonElement>("arqueosserviciosexternos", id, "registrarcomisiones",
                new { fecha, cuentas }, ct);
        }

        public Task<JsonElement> PeriodoAsync(string desde, string hasta, CancellationToken ct = default) =>
            api.ConsultarResultadoAsync<JsonElement>("serviciosexternos/resumen",
                new Dictionary<string, string?> { ["fecha_desde"] = desde, ["fecha_hasta"] = hasta }, ct);
    }
}
```

Las capturas usan `decimal` dentro de su precisión admitida. Las sumas arbitrariamente grandes deben permanecer como cadenas/`JsonElement` hasta formatearlas con una política explícita; no convertirlas a `double`. Verificar en UI la escala y límites antes de enviar `baseUsd`; el servidor rechaza más de dos decimales y no admite redondear silenciosamente el importe de un comprobante.

Usar los métodos genéricos del cliente administrativo para listar, editar y anular. Para mostrar una vista previa, leer `Resultado` del resumen, `totales.ganancia_nio` como cadena nullable y `completo` como booleano; no leer el `resumen` null de una cabecera en borrador como si fuera cero.

La firma anterior de `CapturarAsync` se conserva para los servicios remunerados y provisionales. Para movimientos sin comisión usar `CapturarSinComisionAsync`, que exige motivo. No enviar `ganancia_nio=0` para declarar un cero: el servidor calcula la ganancia a partir de la clasificación. Al cambiar un movimiento sin comisión a un servicio remunerado mediante PATCH, enviar `motivo_sin_comision:null` junto con el nuevo servicio.

## 14. Errores y aceptación

401: sesión ausente. 403: usuario inactivo o sin rol administrador. 404: registro inexistente. 409: fecha/clave/enlace duplicado o restricción de dependencia. 422: campos incorrectos, servicio incompatible, base USD ausente/fuera de rango, huella desactualizada, periodo cerrado o cambios sobre un cierre. Conservar y mostrar mensajes por campo del error Laravel; no tratar un HTTP 422 como éxito por el contenido del body.

Casos que debe verificar el escritorio:

1. Cajero no accede a ninguna ruta nueva; administrador sí. Solo hay una terminal de captura.
2. Diez cuentas BANPRO generan C$50; once C$60.50; veintiuna C$126; treinta y una C$217, incluso con agencias y monedas mezcladas.
3. Filtrar o paginar no modifica la tarifa diaria calculada por la API.
4. Los límites de cada intervalo y los extremos USD0.01/1500 se aplican con dos decimales; una base faltante queda pendiente.
5. La comisión de un pago USD se muestra en NIO. El principal, su neto y la ganancia son conceptos diferentes.
6. Una referencia puede repetirse con distinta clave. Reintentar una misma clave no duplica filas.
7. Cambiar la captura después de mostrar un resumen invalida su huella. El cierre exige nueva revisión.
8. Un cerrado no admite editar o anular sus filas. Su consulta mantiene las tarifas originales.
9. Emitir dos veces con el mismo corte devuelve el mismo reporte. Reconocer dos veces con la misma fecha/cuenta no duplica el cargo.
10. Reconocer ganancias no crea efectivo; el cobro parcial usa la operación real y `aplicaciones`.
11. El capital identifica borradores y ganancias sin reconocer; tras devengarlas desaparece el pendiente sin sumar el principal.
12. El resumen mensual suma cierres diarios, informa borradores y conserva como ausentes los días no capturados.
13. Un fondeo clasificado `sin_comision` conserva principal, exige motivo, no aumenta el tramo y permite cerrar con ganancia cero. Un `sin_clasificar` mantiene el cierre pendiente.

Esquema: [2026_11_05_000005_servicios_externos.php](migrations/2026_11_05_000005_servicios_externos.php). Evidencia del Excel: [ANALISIS_ARQUEO_CENTRALIZADO_REMESAS.md](ANALISIS_ARQUEO_CENTRALIZADO_REMESAS.md). No se han ejecutado migraciones en una base real ni se ha desarrollado la pantalla del repositorio externo C# desde este proyecto de API.

## 15. Validación de esta entrega

- `tests/Schema/servicios_externos_api.php`: 393 comprobaciones nuevas, además de la regresión de caja. Incluye límites, clasificación, huellas, cierre, reporte, cobro parcial, permisos y referencias.
- `tests/Schema/servicios_externos_capital.php`: 77 comprobaciones nuevas, además de la regresión administrativa. Verifica devengo, anulación/reemisión, ausencia de doble contabilización y cierre financiero real del flujo de prueba.
- `tests/Schema/servicios_externos_revision.php`: 97 comprobaciones adicionales en nueve escenarios aislados de la segunda revisión. Incluye cuenta excluida, separación de proveedores entre solicitudes, precisión de sumas y cortes, fechas ausentes, datos previos inconsistentes, atomicidad y año bisiesto.
- `tests/Schema/servicios_externos_sin_comision.php`: 148 comprobaciones adicionales sobre las respuestas comerciales confirmadas. Verifica motivo, contadores, tramo único, reporte de principal, cero sin cargo ficticio y compatibilidad con borradores/cierres V1.
- `tests/Schema/caja_migrations.php`: 534 verificaciones; creación y reversión en SQLite en memoria, tablas protegidas sin cambios y 407 sentencias MySQL compiladas sin conectar a MySQL.
- Regresiones de Libro Diario y su segunda revisión, cuentas personales y administración: aprobadas.
- El bloque C# de esta guía se integra en los ocho bloques de V4, compilados con C#10 y SDK .NET9.0.320, cero errores y advertencias. La verificación conjunta supera 355 condiciones y 50 solicitudes HTTP simuladas; el detalle y los DTOs de lectura están en V4.

Las pruebas no cargan `.env`, no se conectan a la base de la aplicación y no ejecutan macros de Excel. No equivalen a una prueba de MySQL en ejecución, concurrencia real o conectividad/login del escritorio contra un servidor publicado. La migración queda preparada para un despliegue posterior autorizado.

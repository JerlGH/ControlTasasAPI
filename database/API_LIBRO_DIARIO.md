# Libro Diario: implementación y contrato de API

**Nuevo origen de reporte central:** [API_SERVICIOS_EXTERNOS.md](API_SERVICIOS_EXTERNOS.md) documenta la captura de la única terminal administrativa, el cálculo de ganancias y la generación de un reporte confirmado desde su detalle. Agrega `id_arqueo_externo` nullable de solo lectura y su filtro en `reportesremesascentrales`; se conserva la perspectiva central/POS de esta guía.

Entrega original del 5 de noviembre de 2026. Su contrato se incorpora a [la integración C# V4](INTEGRACION_API_CSHARP_V4.md). Implementa el [análisis del Libro Diario AirPak](ANALISIS_LIBRO_DIARIO_AIRPAK.md) sobre los registros existentes de caja y administración. Los ejemplos usan IDs ficticios; deben reemplazarse por los de la instalación.

## 1. Función y límites

El Diario reúne los movimientos de una fecha de todo el grupo, presenta 31 indicadores por cajero y agencia, compara las declaraciones con fuentes centrales y financieras, registra revisiones y archiva el corte. Los importes de las clasificaciones se obtienen de las operaciones originales: no hay un segundo ingreso/egreso por clasificar o revisar.

Se implementan:

- Aperturas, movimientos bancarios, pases, entre agencias, gastos, CxC/CxP y remesas de cada cajero.
- Diferencias originales por moneda y equivalente NIO con la tasa individual: `diferencia_NIO + diferencia_USD × tasa_referencia`. No se vuelve a sumar la utilidad al esperado calculado por caja.
- Conciliación agregada y por relaciones de pases/entre agencias; igualdad de totales no garantiza correspondencias individuales.
- Reporte central independiente de remesas por agencia, banco, moneda y dirección; comparación con perspectiva inversa al cajero.
- Clasificación de banco/cajeros, cambio de tesorería, adicionales, gastos, cuentas particulares, remesas POS y efectivo AirPak.
- Detalle completo de CxC/CxP de los cajeros, sin límite de 17 partidas y con el importe/dirección/moneda de origen.
- Seis saldos: banco principal, POS La Fise y POS Banpro, cada uno en NIO/USD.
- Continuidad contra el corte anterior, revisiones con evidencia y versiones cerradas que no se recalculan desde datos actuales.
- Enlace opcional con arqueo madre y control financiero; detección de saldos reconocidos después del corte.

No se ejecutan macros ni vínculos de Excel. La fuente central se captura por API; no se incluye importador Excel ni conciliación con extractos del banco. El bloque MC clasifica operaciones reales de tesorería con agente y referencia; no crea mesas de cambio ni presupone una tasa a partir de una nota. Las cuentas particulares, pagos, depósitos y préstamos siguen administrándose con los procesos financieros existentes. Clasificar un movimiento en el Diario no aplica un abono o deuda adicional.

## 2. Esquema y compatibilidad

Migración: `2026_11_05_000004_libro_diario.php`. Las migraciones protegidas y sus tablas no se modifican.

| Tabla nueva | Modelo | Propósito |
| --- | --- | --- |
| libros_diarios | LibroDiario | Fecha, versión, trabajadores esperados, referencia anterior, reporte central, resultado y cierre. |
| fuentes_libro_diario | FuenteLibroDiario | Fotografía por cajero, arqueo/agencia, estado y huella. Se genera al recalcular/cerrar. |
| reportes_remesa_central | ReporteRemesaCentral | Cabecera de la fuente independiente. |
| detalles_remesa_central | DetalleRemesaCentral | Ocho combinaciones por agencia, incluidos los ceros declarados. |
| cuentas_libro_diario | CuentaLibroDiario | Seis asignaciones a cuentas financieras existentes. |
| partidas_libro_diario | PartidaLibroDiario | Clasificación y comprobantes de operaciones financieras existentes. |
| detalles_cuenta_diario | DetalleCuentaDiario | Identificación de cuenta/contraparte para cada movimiento `varios` del cajero. |
| revisiones_libro_diario | RevisionLibroDiario | Revisiones sucesivas, inmutables, vinculadas a la huella del control revisado. |

La única columna añadida a una tabla existente es `arqueo_madre.id_libro_diario`, FK nullable. Se añade en la nueva migración después de crear `libros_diarios`. No se cambian las categorías, importes ni contratos de captura del cajero.

Las escrituras comparten la transacción y el bloqueo financiero existentes, requieren fecha posterior al último cierre financiero y quedan en `historial_financiero`. Las FK restringen borrados. Las anulaciones conservan registros y liberan únicamente sus claves activas para permitir correcciones.

## 3. Rutas, acceso y respuestas

Base: `/api/administracion`. Todas las rutas exigen `Authorization: Bearer <token>` de administrador activo, también las lecturas. Se conservan las envolturas de administración:

- Creación: HTTP 201, `{"Registro": {...}, "Status": 201}`.
- Consulta/actualización/anulación: HTTP 200 y `Registro`.
- Listados: HTTP 200, `Registros`, `Paginacion` (`total`, `pagina`, `por_pagina`) y `Status`.
- Resumen/recalcular/cerrar: HTTP 200 y `Resultado`.
- Validación o regla de negocio: 422; sin autenticación: 401; sin permiso: 403; ID inexistente: 404; clave activa duplicada: 409.

| Recurso | GET colección / GET {id} / POST | PUT o PATCH {id} / DELETE {id} |
| --- | --- | --- |
| librosdiarios | Sí | Solo borrador. DELETE requiere `motivo`. |
| reportesremesascentrales | Sí | Solo borrador. |
| detallesremesascentrales | Sí | Solo con reporte padre en borrador. |
| cuentasdiario | Sí | Solo con Diario padre en borrador. |
| partidasdiario | Sí | Solo con Diario padre en borrador. |
| detallescuentasdiario | Sí | Solo con Diario padre en borrador. |
| revisionesdiario | Sí | No. Se registra una revisión posterior, conservando las anteriores. |

| Método | Ruta adicional | Función |
| --- | --- | --- |
| GET | /librosdiarios/{id}/resumen | Calcula el borrador o devuelve exactamente el resultado cerrado. |
| POST | /librosdiarios/{id}/recalcular | Persiste fuentes, resultado y huella del borrador. Cuerpo vacío. |
| POST | /librosdiarios/{id}/cerrar | Exige `{"confirmar_fuentes": true}` y todas las condiciones de cierre. |
| POST | /reportesremesascentrales/{id}/confirmar | Confirma una fuente completa; cuerpo vacío. |

Son 43 combinaciones método/ruta nuevas, sin contar HEAD. Fuentes es una salida calculada del resumen; no tiene CRUD de importes manuales.

Listados: `page`, `per_page` entre 1 y 100; filtros de IDs, estado, banco/canal, moneda o bloque cuando sean campos del recurso. Los hijos admiten `id_libro_diario` o `id_reporte` según corresponda. `fecha_desde`/`fecha_hasta` filtran fecha operativa en cabeceras y fecha de creación en recursos sin `fecha`; para obtener los hijos de una jornada debe filtrarse por su cabecera.

Los importes administrativos son cadenas decimales de hasta ocho decimales y las tasas de caja conservan seis. Usar `decimal` en C#, JSON con punto y los códigos `NIO`/`USD`. No enviar IDs de autor, estados, huellas de cierre ni resultados calculados en las altas/ediciones; el servidor los determina. PUT y PATCH admiten actualización parcial. La identidad de las partidas se conserva: para cambiar operación, cuenta, banco, moneda o dirección se anula el registro y se crea la corrección.

## 4. Apertura del Diario

`POST /librosdiarios`:

```json
{
  "fecha": "2026-11-05",
  "usuarios_esperados": [12, 15, 19],
  "id_reporte_remesas": null,
  "motivo_apertura": "Primer corte: saldos bancarios documentados",
  "observacion": "Control del día"
}
```

`fecha` y una lista no vacía de usuarios distintos son obligatorios. Se asignan versión incremental de esa fecha e `id_anterior` al último Diario cerrado de fecha anterior. La lista es la cobertura declarada del grupo; si existe un arqueo vigente de esa fecha fuera de la lista, aparece un pendiente que impide cerrar. Las ausencias esperadas deben resolverse expresamente en la selección de fuentes, sin inventar arqueos de importe cero.

El primer corte exige motivo de apertura para cerrarse. Al existir anterior, se copian sus referencias válidas a cuentas, sin generar movimientos de dinero ni copiar saldos como ingresos. Una cuenta desactivada/incompatible queda pendiente de sustitución sin impedir crear el borrador. Los saldos iniciales se calculan desde el auxiliar financiero; si difieren del cierre anterior, queda un control de continuidad que exige justificación con evidencia. También se detectan movimientos añadidos entre cortes aunque su neto sea cero. Cada resumen de borrador utiliza el último cierre disponible de fecha anterior; si ese cierre aparece después de abrir el borrador, sus controles y huellas se actualizan. Al recalcular/cerrar se persiste la referencia utilizada.

Crear otra versión de la misma fecha no modifica el corte anterior. Las revisiones, clasificaciones y reporte de la nueva versión se deben verificar nuevamente. La cabecera nueva queda en borrador y sin resultado persistido: consultar `/resumen` para datos actuales; `GET /librosdiarios/{id}` devuelve la cabecera y el último resultado persistido, que puede estar desactualizado mientras sea borrador.

## 5. Seis asignaciones de cuentas

`POST /cuentasdiario`:

```json
{"id_libro_diario": 7, "id_cuenta": 23, "canal": "lafise"}
```

Canales exactos: `bancentro`, `lafise`, `banpro`. La moneda se obtiene de la cuenta. `bancentro` exige tipo `banco`; los otros canales exigen tipo `pos`. Deben ser cuentas activas, existentes en esa fecha. No se puede usar una cuenta dos veces ni repetir canal/moneda en el mismo Diario. Se requieren las seis combinaciones para cerrar.

Las cuentas se crean/administran con `/cuentas` y se mueven con `/operaciones`. Las seis requieren `rubro_capital: banco`, evitando que un saldo del Diario desaparezca del capital por quedar excluido. Una cuenta bancaria Banpro distinta de su POS no debe asignarse al canal POS. El nombre de canal representa la función del Excel; la cuenta financiera conserva su propia identidad y titular.

## 6. Reporte central de remesas

`POST /reportesremesascentrales`:

```json
{
  "fecha": "2026-11-05",
  "corte_at": "2026-11-05 18:00:00",
  "referencia": "CENTRAL-20261105-01",
  "fuente": "Registro central de remesas",
  "agencias_esperadas": [2, 4],
  "id_documento": null,
  "observacion": "Fuente independiente de los cajeros"
}
```

Fecha, corte de esa fecha, referencia única, fuente y lista de agencias distintas son obligatorios. `id_documento` y observación son opcionales.

`POST /detallesremesascentrales`:

```json
{"id_reporte": 5, "id_subagencia": 2, "banco": "lafise", "moneda": "USD", "tipo": "Entrada", "monto": "150.00"}
```

Todos esos campos son obligatorios; `observacion` es opcional. Bancos: `lafise`, `banpro`. Dirección: `Entrada`, `Salida`, **desde central/POS**. Los montos no pueden ser negativos. Por cada agencia se declaran ocho líneas: dos bancos × dos monedas × dos direcciones. Declarar cero cuando corresponda; una línea ausente no se convierte en cero confirmado. Solo existe una línea vigente por combinación.

Confirmar con `/reportesremesascentrales/{id}/confirmar` y asignar el reporte a la cabecera del Diario mediante PATCH. Para cerrar, el reporte debe estar confirmado, ser de la misma fecha y cubrir exactamente las agencias de los arqueos del Diario. Los textos habituales `La Fise`, `Lafise`, `Bancentro`, `Banpro` de remesas de caja se normalizan al compararse; un banco no reconocido genera una observación, conservando el movimiento original.

Las entradas del cajero se comparan con salidas centrales; las salidas del cajero, con entradas centrales. Para mantener los signos del Excel, la diferencia es `entrada_cajero − salida_central` o `entrada_central − salida_cajero`. Se compara cada agencia/banco/moneda/dirección, sin compensar monedas ni entradas contra salidas.

## 7. Clasificaciones financieras y entrega AirPak

Primero se registra o localiza la operación financiera real en `/operaciones`. Después, `POST /partidasdiario`:

```json
{"id_libro_diario": 7, "id_operacion": 81, "bloque": "banco_cajeros", "observacion": "Traslado recibido en banco"}
```

`id_libro_diario`, `id_operacion` y `bloque` son obligatorios. Opcionales: `id_contraparte`, `id_documento`, `receptor`, `referencia`, `observacion`, con las condiciones siguientes:

| Bloque | Condición |
| --- | --- |
| banco_cajeros | Transferencia financiera enlazada al movimiento bancario original del cajero, contra banco principal. |
| efectivo_airpak | Egreso enlazado a salida bancaria del cajero en efectivo, sin cuenta banco/POS. Requiere receptor y documento o referencia. |
| remesa_pos | Transferencia enlazada a remesa original y al POS de su banco/moneda. |
| cambio_tesoreria | Movimiento real de cuenta seleccionada, con agente/contraparte y referencia. Las patas monetarias se registran como operaciones reales separadas, sin crear mesas protegidas. |
| adicional | Otro movimiento real de una cuenta seleccionada. |
| gasto | Movimiento real de cuenta seleccionada, clasificado como gasto. El gasto/aplicación contable se registra con los módulos existentes. |
| cuenta_particular | Movimiento real de cuenta seleccionada, con titular/contraparte. Los abonos/retiros/aplicaciones usan los módulos existentes. |

La operación debe ser de la fecha del Diario. Si tiene origen en caja, debe ser de un arqueo cerrado de un usuario incluido. Una operación solo puede clasificarse una vez por versión. Los orígenes bancarios y remesas no pueden ocultarse en otro bloque para eludir su conciliación.

Para una salida bancaria del cajero depositada en banco, `/operaciones` usa `tipo: transferencia`, `origen_tipo: entrada_salida`, `id_origen` del movimiento y solamente `id_cuenta_destino`. Para una entrada del cajero se usa solamente `id_cuenta_origen`. La operación de reconocimiento no modifica el arqueo.

Para entrega directa AirPak, la operación usa `tipo: egreso`, el mismo origen de salida bancaria y ninguna cuenta banco/POS. La partida identifica a quien entrega mediante su origen y exige a quien recibe:

```json
{"id_libro_diario": 7, "id_operacion": 82, "bloque": "efectivo_airpak", "receptor": "Receptor del comprobante", "referencia": "RECIBO-000123"}
```

El control compara el movimiento físico declarado con lo reconocido en banco más AirPak, por moneda y dirección. Los movimientos declarados con `medio: transferencia` no son otra entrega física de caja. Las métricas de declaraciones conservan esos registros y su medio; el cuadre físico no los suma como efectivo.

Los seis saldos se calculan con `saldo_inicial + entradas − salidas` del auxiliar financiero. Para POS también se compara lo reconocido como remesas con las entradas/salidas centrales. El neto central no se vuelve a agregar al auxiliar: se presenta un saldo esperado independiente para la comparación. Otros movimientos y retiros mantienen su clasificación y fuente.

## 8. Detalle completo de cuentas de cajeros

La categoría existente `entradas_y_salidas.categoria = varios` corresponde al bloque F80:F83 del Excel, presentado como CxC/CxP en W:Z del Diario. Se conservan todos sus movimientos vigentes, incluidos los declarados por transferencia. No se amplía ni se cambia el enum de caja.

Por cada movimiento, `POST /detallescuentasdiario`:

```json
{"id_libro_diario": 7, "id_entrada_salida": 93, "id_cuenta_auxiliar": 30, "id_operacion": 84, "observacion": "Abono identificado del titular"}
```

Se exigen Diario, origen y al menos una identificación: `id_cuenta_auxiliar` o `id_contraparte`. La cuenta auxiliar debe ser `por_cobrar`, `por_pagar` o `fondo` y existir en esa fecha; si también se informa contraparte, debe ser compatible con su titular. Opcionales: `id_operacion`, `id_documento`, observación. El origen debe ser `varios`, estar vigente y pertenecer al arqueo cerrado del cajero del corte.

No se reciben monto, moneda ni dirección: se leen del movimiento original. No se puede detallar el mismo origen dos veces por versión. Si se informa operación financiera, debe reconocer exactamente ese mismo origen y fecha; para declaraciones por transferencia que no representan nueva entrega física, se conserva el detalle de origen sin forzar una operación física adicional.

El resumen contiene cada partida, su origen, cajero/agencia, cuenta, dirección, moneda e importe. Las diferencias se calculan como `detalle − declarado` para entrada NIO, salida NIO, entrada USD y salida USD. Si falta cualquier partida, el Diario queda incompleto y no puede cerrarse por una justificación del agregado. Las adiciones/reducciones de deuda sin dinero no se incluyen como movimientos de caja; siguen en el auxiliar financiero.

## 9. Resumen y revisiones

`GET /librosdiarios/{id}/resumen` devuelve `Resultado` con:

| Campo | Contenido |
| --- | --- |
| fecha, version | Identidad del corte. |
| metricas | 31 indicadores totales disponibles. |
| por_agencia | Mismos indicadores por agencia. |
| fuentes | Arqueo, operador/agencia, movimientos, cambio, cheques, conteos, resumen y huella por cajero. |
| movimientos | Todos los movimientos individuales por bloque con IDs y registro de origen. |
| cobertura | Cantidades esperadas, recibidas y cerradas. |
| pendientes, completo | Faltas estructurales; los subtotales disponibles no significan cobertura completa. |
| detalle_cuentas, cuadre_cuentas | Partidas completas y cuatro diferencias. |
| cuadre_banco_cajeros | Declarado físico, reconocido banco, efectivo AirPak y orígenes sin contraparte. |
| reporte_remesas | Cabecera y líneas de la fuente central. |
| partidas | Clasificaciones y operaciones financieras. |
| saldos | Seis cuentas, apertura, movimientos, cierre, continuidad y comparación POS. Claves como `lafise:USD`. |
| controles | Clave, coincidencia, datos, huella, necesidad de revisión y revisión vigente. |
| revisiones_pendientes | Claves que aún requieren revisión o justificación. |
| con_diferencias | Conserva si existen controles con diferencias, incluso si se justifican. |

Métricas: `diferencia_equivalente_nio`, `apertura_nio`, `apertura_usd` y cuatro campos por cada bloque `bancario`, `pases`, `entre_agencias`, `gastos`, `cuentas`, `lafise`, `banpro`. Ejemplo: `bancario_entrada_nio`, `bancario_salida_nio`, `bancario_entrada_usd`, `bancario_salida_usd`.

Para revisar, enviar la clave y huella **devueltas por el resumen actual** a `POST /revisionesdiario`:

```json
{
  "id_libro_diario": 7,
  "clave_control": "cuenta:bancentro:NIO",
  "huella": "SUSTITUIR_POR_LOS_64_CARACTERES_DEVUELTOS",
  "resultado": "revisado",
  "detalle": "Verificado contra los comprobantes de la jornada"
}
```

El valor ilustrativo de huella debe reemplazarse por el hexadecimal real, no enviarse literalmente. Claves habituales: `cajero:{id}`, `detalle_cuentas`, `reporte_central`, `cuenta:{canal}:{moneda}`, `apertura:{canal}:{moneda}`, `remesa:{agencia}:{banco}:{moneda}:{direccion}`, `pos:{canal}:{moneda}`, `par:{bloque}:{id}` y `banco_cajeros:{moneda}:{direccion}`.

- `revisado`: solo un control coincidente.
- `observado`: conserva el pendiente y bloquea el cierre cuando exige revisión.
- `justificado`: solo un control con diferencia; exige detalle y documento o referencia. Conserva importe, signo y falta de coincidencia.

No se puede revisar una huella distinta de la actual. Al cambiar los datos, una revisión anterior no aprueba el control cambiado. Se utiliza la última revisión de la misma clave/huella; una observación posterior vuelve a dejarlo pendiente. Cada cajero, cada cuenta, el reporte central y el detalle de CxC/CxP requieren revisión expresa aunque cuadren; las diferencias de los demás controles también requieren resolución/justificación.

## 10. Cierre y continuidad

El cierre requiere confirmación de fuentes, arqueos completos y cerrados, tasas individuales presentes, reporte central confirmado con cobertura exacta, seis cuentas válidas, movimientos bancarios clasificados, detalle íntegro de CxC/CxP y todas las revisiones exigidas vigentes. Las partidas, titulares, documentos y cuentas se revalidan al resumir/cerrar; cambiar después las asignaciones de POS o desactivar referencias puede dejar pendientes estructurales. Una justificación no sustituye fuentes o clasificaciones ausentes/inválidas.

Un corte con incidencias justificadas puede cerrarse y conserva `con_diferencias: true`. No genera una deuda ni un descuento al trabajador. Para gestionar una incidencia financiera debe utilizarse su módulo y su evidencia.

Se guardan fuentes, resultado, revisión, autor de cierre, momento y huella. Un corte cerrado y sus hijos no admiten edición ni anulación desde estos endpoints. El resumen cerrado devuelve la fotografía guardada. Una corrección se trata mediante nueva versión y los ajustes financieros correspondientes en un periodo abierto.

## 11. Conexión con arqueo madre y capital

`POST /arqueosmadre` admite ahora `id_libro_diario` opcional, también disponible al actualizar un borrador. Debe apuntar a un Diario cerrado de la misma fecha, grupo y exactamente los mismos usuarios esperados. No se admite vincular un Diario de grupo a un arqueo madre de una sola agencia.

Al recalcular/cerrar el arqueo madre, `totales.libro_diario` guarda ID, fecha, versión, huella, los seis saldos e indicador de diferencias. Estos importes no se agregan otra vez al contado de cajeros.

En `/capital`, si el arqueo madre tiene ese corte, sus seis saldos alimentan bancos y el capital de trabajo seleccionado. Las otras cuentas financieras conservan su tratamiento previo. Si un saldo actual del auxiliar difiere del recibido, se informa `saldo_distinto_libro_diario` con ambos valores. También se verifica la huella del historial financiero, informando `movimientos_distintos_libro_diario` aunque nuevas entradas/salidas se compensen. Si aparece otro arqueo vigente de la fecha, se informa `fuentes_distintas_libro_diario`, tanto en la consolidación enlazada como en capital. Estos pendientes bloquean el cierre correspondiente hasta resolver el corte/versiones.

En la fuente de cada cuenta se exponen `origen_saldo` y `saldo_ledger_actual`; la respuesta agrega `libro_diario`. Los nuevos saldos del Diario contienen `huella_auxiliar`, `huella_auxiliar_apertura`, `huella_auxiliar_anterior` e `historial_apertura_coincide`. El resumen agrega `id_diario_anterior`. Son datos calculados, no campos de escritura. Sin enlace al Diario, se mantiene el cálculo anterior desde el auxiliar.

## 12. Verificación y operación

Comandos de regresión, todos con SQLite en memoria, sin cargar `.env`:

```text
php tests/Schema/caja_migrations.php
php tests/Schema/cuentas_personales_api.php
php tests/Schema/libro_diario_api.php
php tests/Schema/libro_diario_revision.php
```

La prueba de esquema crea/revierte las migraciones, conserva tablas/datos protegidos y compila DDL MySQL sin conexión. La de cuentas personales incluye caja y administración. La del Diario verifica equivalencia con Eveling 08, cobertura, signos, entregas AirPak, partidas sin doble contabilización, clasificaciones obligatorias, revisión de diferencias, invalidación por cambio de huella, cierres inmutables, nuevas versiones, continuidad y conexión con capital.

No se ha ejecutado la migración contra la base configurada del proyecto ni contra producción durante esta implementación. La validación MySQL es de DDL compilado, no una ejecución en un servidor real. Los escenarios y correcciones de la segunda revisión están en [REVISION_LIBRO_DIARIO.md](REVISION_LIBRO_DIARIO.md).

## 13. Reducciones de obligaciones y compatibilidad de cuentas

La segunda revisión corrigió la inconsistencia previa de reducciones manuales frente a cargos automáticos. Para una reducción sin dinero: crear el abono manual en `movimientoscuenta` con motivo y aplicarlo al cargo correspondiente mediante `aplicacionescuenta`, en la misma cuenta y dentro de sus pendientes. Se admiten cargos automáticos; el abono debe ser manual. Los abonos automáticos siguen administrándose desde su origen.

Las aplicaciones de dinero contra un cargo o documento con cargo asociado verifican tanto su pendiente como el saldo neto de cuenta, a la fecha de aplicación y considerando movimientos posteriores existentes. Una obligación de 100 con reducción manual de 20 no admite un pago de 100; después de imputar la reducción, el pago de 80 deja saldo y pendiente en cero. La reducción de deuda no cambia por sí sola el importe nominal del gasto reconocido ni crea dinero. Las aplicaciones monetarias a cargos automáticos siguen usando su destino específico (`id_gasto`, `id_obligacion`, etc.); la excepción de `aplicaciones.id_cargo` para préstamos personales no cambia.

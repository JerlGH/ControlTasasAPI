# Segunda revisión de servicios externos

Fecha: 2026-11-06. Alcance: Excel original, reglas indicadas por el usuario, API de la terminal administrativa y conexiones con Libro Diario, comisiones y capital.

No se modificó el Excel ni las migraciones o tablas maestras protegidas. La revisión corrige validaciones del servicio/controlador, agrega pruebas y actualiza la documentación. Tras las confirmaciones del usuario se añadió `motivo_sin_comision` a la migración no protegida `2026_11_05_000005_servicios_externos.php`, al modelo y a las validaciones. No se ejecutaron migraciones en una base real.

## 1. Resultado

Se reprodujeron tres fallos de validación/integración y una carencia de visibilidad de cobertura mediante rutas reales con SQLite en memoria. Se corrigieron y se añadió un control de consistencia sobre devengos ya registrados. También se precisó el contrato de hora del corte para evitar un reintento que compare más precisión de la que guarda la base.

Las tarifas fijas coinciden con las instrucciones del usuario. Las dos consultas comerciales se resolvieron posteriormente: tarifa única para todas las transacciones de cuentas BANPRO del día y registro explícito de movimientos sin comisión.

## 2. Cuenta por cobrar excluida del capital

**Antes:** `registrarcomisiones` aceptaba una cuenta `por_cobrar/NIO` con `rubro_capital=excluido`. La comisión y su cargo se creaban correctamente, y la cobertura marcaba el devengo como reconocido. El capital omitía el saldo de esa cuenta por estar excluida.

**Corrección:** el reconocimiento exige `rubro_capital=pendiente`. La cobertura también revisa las comisiones previas: importe esperado, naturaleza cobrar, tipo proveedor, moneda NIO y cuenta central por cobrar incluida en pendientes. Si no corresponde, devuelve `comision_externa_inconsistente` y no permite cerrar financieramente como si estuviera resuelto.

La comprobación de datos previos no exige que una cuenta histórica siga activa. Una deuda correctamente contabilizada no desaparece porque su cuenta se haya inactivado después. El alta de un devengo nuevo sí requiere cuenta activa.

## 3. Cuenta compartida entre proveedores mediante solicitudes distintas

**Antes:** el body con dos proveedores rechazaba cuentas repetidas, pero dos llamadas de un proveedor cada una podían usar la misma cuenta: una para LAFISE y otra para BANPRO. Se mezclaba el auxiliar de ambos proveedores pese a que el contrato pedía cuentas separadas.

**Corrección:** antes de reconocer una comisión se buscan usos externos vigentes de esa cuenta por el otro banco, también en otras fechas. Se rechaza la mezcla. Las solicitudes siguen siendo atómicas: un error en el segundo proveedor no deja comisión ni cargo del primero.

La cobertura de capital también detecta mezclas previamente permitidas por la versión anterior, considerando las comisiones reconocidas hasta la fecha del corte. No usa una comisión de una fecha futura para invalidar un corte anterior.

El banco no se infiere del nombre libre de la contraparte. La selección inicial de una cuenta dedicada al proveedor correcto sigue siendo una responsabilidad administrativa explícita.

## 4. Sumas que no caben en el reporte central

**Antes:** dos filas individualmente válidas podían sumar un total mayor que los 16 enteros disponibles en `detalles_remesa_central.monto`, de tipo `DECIMAL(24,8)`. SQLite no aplica esa capacidad como MySQL, por lo que una prueba de flujo normal no revelaba el problema.

**Ejemplo reproducido:** dos principales de `9000000000000000` en la misma combinación producen `18000000000000000`, que tiene 17 enteros.

**Corrección:** se revisan todas las líneas calculadas antes de emitir el reporte. Si un total excede la precisión, la API responde 422 en `reporte` y no deja un documento parcial. El resumen mantiene su suma exacta; no se recorta, divide ni redondea para forzarla a entrar. No se amplió arbitrariamente el esquema de los reportes.

## 5. Fecha sin registro frente a un día cerrado en cero

**Antes:** la consulta de periodo devolvía días cerrados y borradores, pero para reconocer una fecha ausente C# debía reconstruir todo el calendario. Era fácil mostrar una fecha omitida como si estuviera confirmada en cero.

**Corrección:** `Resultado.fechas_sin_registro` enumera las fechas inclusivas sin cabecera vigente. Se distinguen los tres casos: cerrado en cero, borrador y sin registro. Una cabecera anulada no cubre el día. Se verificaron el 29 de febrero y el límite inclusivo de 366 días.

Esto informa la cobertura. No establece un calendario laboral ni obliga a capturar domingos o feriados, porque esa regla no fue proporcionada. El cierre financiero detecta los registros existentes pendientes, pero no puede demostrar por sí mismo que nunca faltó un día obligatorio del que no se abrió cabecera.

## 6. Precisión y significado del corte

El campo de emisión admitía cualquier `date`, incluidos valores ISO con fracciones, pero la base y la respuesta conservan segundos. Un reintento con fracciones podía dejar de coincidir con lo almacenado.

El nuevo endpoint de emisión exige `yyyy-MM-dd HH:mm:ss`, sin fracciones ni zona en el texto, en hora local operativa. Se comprueba el rechazo del formato incompatible y la recuperación del mismo reporte al repetir el corte admitido. Esto solo precisa el endpoint nuevo de emisión; no cambia el contrato de captura manual de los reportes antiguos.

El corte representa un reporte de la fecha completa. El detalle externo no tiene hora real de ejecución por fila, por lo que **no ofrece un corte intradiario calculado**. No deben excluirse filas por `created_at`: pueden haberse digitado después de la operación.

## 7. Segunda lectura del Excel

Se volvieron a inspeccionar validaciones, dibujos, referencias externas, filas/columnas ocultas y posibles comentarios. No hay comentarios, filas/columnas ocultas ni destinos externos. Las listas de validación confirman agencias, cajeros, bancos, dirección y moneda; no incluyen clase de servicio, tarifa ni indicador de comisión.

La lista de cajeros del Excel no implica que cada uno tenga que capturar este módulo. La terminal única y la captura administrativa provienen de la aclaración del usuario.

Se localizaron referencias que requieren clasificación humana; por ejemplo:

| Hoja / celda | Texto | Qué puede concluirse |
| --- | --- | --- |
| `07-09!F4` | `REPOSICION CED` | No basta el nombre de la columna «N° Remesa» para clasificarla como remesa con comisión. |
| `11-09!F7` | `REPOSICIN DE CEDULA` | Hay referencias descriptivas, no solo identificadores de remesa. |
| `16-09!F8` | `RETIRO TD` | Puede ser una operación de cuenta, pero el archivo no aporta su clasificación de comisión. |
| `17-09!F25` | `tarjeta ` | El texto no permite asignar una tarifa de forma fiable. |
| `02-10!F28` | `RETIRO CUENTA` | Un retiro de cuenta y una remesa extranjera son clases distintas aunque compartan el bloque del Excel. |
| `05-10!F46` | `RETIRO DE TD` | Requiere corroborar el servicio ejecutado. |
| `12-10!F26` | `REP CEDULA` | Su tratamiento comercial no se puede deducir de una suma o un conteo. |

No se asignó cero ni una comisión a estas filas de manera automática. No se importaron registros ni se ejecutó el VBA.

## 8. Decisiones comerciales confirmadas y límites explícitos

**Método del rango BANPRO.** El usuario confirmó `11 × 5.50 = 60.50`: tarifa única para todas las transacciones de cuentas del día. Se conserva el cálculo implementado. Los movimientos sin comisión, remesas y otros servicios no elevan ese contador.

**Movimientos sin comisión.** El usuario confirmó su existencia. Se implementó `servicio=sin_comision` para ambos bancos, con `motivo_sin_comision` obligatorio. Su ganancia es cero, su principal se conserva en los reportes y el día puede cerrar. No requiere crear un cargo financiero cero. `sin_clasificar` mantiene resultado desconocido y bloquea el cierre.

El tarifario V2 añade la clase nueva sin cambiar ninguna tarifa remunerada. Un borrador V1 la habilita al capturar el primer movimiento de esa clase, con auditoría y conservación de las tarifas. Los cierres V1 mantienen su snapshot. Los contadores con/sin comisión del periodo admiten estos cierres anteriores sin reescribir su JSON.

**Cancelaciones posteriores al cierre.** Una fila incorrecta se corrige o anula mientras el día es borrador. No existe aún un flujo de reverso operacional posterior al cierre que recalcule cantidades o el tramo BANPRO. La anulación/reemisión de una comisión financiera corrige su documento de cobro, pero no altera el número histórico de transacciones. No se debe presentar esa acción como un reverso de servicio. Definir ese flujo requiere conocer el tratamiento de cancelaciones por el proveedor.

**Dinero de la terminal.** Este archivo detalla operaciones y comisiones; no contiene un conteo físico propio con apertura, denominaciones y diferencia de caja de la terminal. El nuevo registro no crea principal en caja o POS automáticamente. Cuando existe un movimiento real de fondos, debe seguir registrado una vez en el módulo financiero/caja correspondiente. El enlace a caja es un contraste, no una segunda contabilización.

**Ganancia estimada y cobro real.** El tarifario calcula comisiones nominales. No calcula deducciones, impuestos o liquidaciones netas del proveedor que no se hayan indicado. La cuenta por cobrar, las reducciones documentadas y los cobros reales siguen en el auxiliar financiero existente.

**Permisos.** El módulo exige administrador activo; el responsable identifica a la persona encargada. No hay un permiso adicional que permita a un usuario cajero capturarlo ni una exclusividad de escritura para un solo administrador. No se cambió ningún rol en `users`.

## 9. Evidencia de prueba y cambios para C#

`tests/Schema/servicios_externos_revision.php` agrega nueve escenarios aislados con 97 comprobaciones, además de las 393 comprobaciones iniciales del módulo y la regresión de caja. El flujo financiero del módulo conserva sus 77 comprobaciones adicionales. Todas se ejecutan con SQLite en memoria y datos ficticios.

Para C# se agregan `fechas_sin_registro`, los contadores con/sin comisión, `motivo_sin_comision`, el servicio nuevo y el pendiente `comision_externa_inconsistente`; también se exige `rubro_capital=pendiente`, separación de cuentas por proveedor entre solicitudes y el formato explícito de `corte_at`. Se conserva el conjunto de rutas y se amplía la migración no protegida existente. El cliente de ejemplo envía el corte como texto, conserva las nuevas propiedades con `JsonElement` y agrega `CapturarSinComisionAsync` sin cambiar la firma anterior de captura.

El contrato actualizado está en [API_SERVICIOS_EXTERNOS.md](API_SERVICIOS_EXTERNOS.md); el análisis original, con su aclaración de alcance, en [ANALISIS_ARQUEO_CENTRALIZADO_REMESAS.md](ANALISIS_ARQUEO_CENTRALIZADO_REMESAS.md).

Tras las confirmaciones se añadieron cinco escenarios con 148 comprobaciones en `tests/Schema/servicios_externos_sin_comision.php`, incluidos diez movimientos remunerados más fondeos y la undécima transacción: el tramo sigue en C$5 hasta esa undécima cuenta y pasa a C$5.50 para las once. También se prueban motivos, reclasificación, cero confirmado, reconocimiento financiero, reporte completo y compatibilidad V1.

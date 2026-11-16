# Segunda revisión del Libro Diario y sus conexiones

5 de noviembre de 2026. Revisión de código, escenarios adversos y correcciones posteriores a la primera implementación. No se modificaron migraciones, maestros protegidos ni datos de una base real. El contrato actualizado está en [API_LIBRO_DIARIO.md](API_LIBRO_DIARIO.md).

## Fallos encontrados y corregidos

| Prioridad | Fallo comprobado | Corrección y prueba |
| --- | --- | --- |
| Alta | Una obligación automática de 100, reducida manualmente en 20, todavía permitía pagar 100. El saldo general y el pendiente podían divergir. | Las aplicaciones monetarias comprueban el pendiente y saldo neto de cuenta, a la fecha y al considerar movimientos posteriores. `aplicacionescuenta` admite imputar un abono manual al cargo automático. Se prueba rechazo de 100 y cancelación con reducción 20 + pago 80. |
| Alta | Cambiar las asignaciones de POS después de registrar las partidas no revalidaba la clasificación. Se podía justificar una diferencia agregada manteniendo partidas en el banco equivocado. | Se reutilizan las validaciones de alta al resumir/cerrar. La asignación incompatible genera `partida_invalida` y bloquea el cierre; una justificación del total no reemplaza esa validación. |
| Alta | Capital comparaba únicamente saldos; una entrada y salida posteriores que se compensaran podían pasar inadvertidas. | Se conserva una huella de los hechos del auxiliar y se compara con el historial actual. Se informa `movimientos_distintos_libro_diario`, incluso con neto cero, y se bloquea el cierre financiero. |
| Alta | Un arqueo nuevo recibido después del Diario cerrado podía quedar fuera de una nueva consolidación enlazada y del control financiero. | Se comparan los IDs de arqueos vigentes de la fecha con los de la fotografía. `fuentes_distintas_libro_diario` impide cerrar el arqueo madre o cierre financiero con fuentes omitidas. El Diario anterior sigue inmutable. |
| Alta | El enlace de pases permitía cajas de agencias distintas si se omitía `id_caja_contraparte`. | La validación compartida de caja y la conciliación del Diario exigen la misma agencia. El movimiento entre sucursales usa `entre_agencias`. La prueba usa tres cajeros: dos de una agencia y uno de otra. |
| Alta | Una cuenta elegida para los seis saldos podía tener `rubro_capital: excluido`; su saldo no alimentaría el capital esperado. | Las asignaciones exigen `rubro_capital: banco`, además de tipo, moneda, vigencia y fecha compatibles. Se prueba rechazo de un POS excluido. |
| Media | Un titular, documento o cuenta auxiliar podía desactivarse después del alta del detalle sin impedir cerrar el Diario. | Las referencias se revalidan y se guardan sus datos en la evidencia del detalle/partida. La referencia inválida queda como pendiente estructural; los cambios de evidencia alteran sus huellas. |
| Media | Un borrador abierto antes del cierre anterior conservaba una referencia obsoleta y podía revisar una continuidad incorrecta. | Los cálculos de borrador consultan el último cierre disponible de fecha anterior. Recalcular/cerrar persiste el ID utilizado. Un cambio de anterior exige revisar los controles cuyo contenido cambió. |
| Media | La continuidad de apertura comprobaba solo importes; movimientos reconocidos tarde podían compensarse y dejar el mismo saldo. | Se compara también la huella del auxiliar de apertura con la del cierre anterior. Se conserva la diferencia monetaria cero junto al control de historial no coincidente. |
| Media | Crear el siguiente Diario fallaba completamente si alguna cuenta heredada había sido desactivada. | Se heredan solo referencias compatibles; el borrador se crea con la cuenta pendiente de reemplazo y no puede cerrarse hasta completar las seis. |
| Media | PATCH de `arqueosmadre.id_libro_diario` rechazaba el enlace por la regla general de relaciones inmutables, aunque el contrato permitía asociarlo a un borrador. | Se permite modificar únicamente ese enlace en borrador, sujeto a fecha, grupo, usuarios y Diario cerrado compatibles. Se prueban asociación y retirada del enlace. |

Seis escenarios adversos iniciales reprodujeron fallos antes de corregirlos. Después se ampliaron las pruebas con cobertura tardía, saldo neto cero en continuidad, exclusión de cuentas, pases y reducción de obligaciones. La prueba de filtro `fecha_hasta` sin `fecha_desde` confirmó que ya funcionaba y no requirió modificación.

## Integridad y precisión

Las huellas del auxiliar utilizan IDs, fecha operativa, tipo, importe normalizado e ID de operación; además, identifican cuenta, moneda, apertura y saldo inicial. No dependen de notas, timestamps técnicos ni del orden de propiedades que MySQL utilice para almacenar JSON. Una operación financiera no se contabiliza de nuevo por clasificarla o revisar sus datos.

Las consultas internas del Diario/capital que solo necesitan hechos y saldos omiten el cálculo de pendiente por cada asiento histórico. El estado de cuenta público conserva sus pendientes individuales. Esto evita añadir una consulta de aplicaciones por cada movimiento de bancos al preparar un corte.

Los cortes cerrados mantienen su fotografía. Estas correcciones no reescriben resultados antiguos ni imputan por su cuenta ajustes existentes: para regularizar una reducción se identifica el abono y el cargo correspondiente. Una reducción de deuda no cambia automáticamente el gasto nominal ni crea un ingreso/egreso. La compensación entre cuentas distintas y las partidas de apertura sin cargo individual conservan sus límites ya documentados.

## Verificación

`tests/Schema/libro_diario_revision.php` ejecuta la regresión de caja y Diario y añade casos aislados con rollback propio. `tests/Schema/cuentas_personales_api.php` verifica también administración y cuentas personales. Las pruebas usan SQLite en memoria, sin `.env` ni servidor de datos. El esquema se verifica mediante creación/reversión y compilación DDL MySQL.

Resultado final: 661 verificaciones de caja, 611 de administración, 254 de cuentas personales, 422 del Diario, 308 adicionales de esta revisión y 509 del esquema; todas satisfactorias. Se compilaron 382 sentencias MySQL sin conexión. La comprobación por SHA-256 confirmó que ninguna migración existente cambió durante esta revisión.

Las pruebas comprueban autorización, referencias, clasificación, huellas, cierre con incidencias justificadas, resultados congelados, continuidad, cobertura, deuda neta y conservación de maestros protegidos. No sustituyen una prueba de concurrencia y ejecución de migraciones en un servidor MySQL real. Los cortes anteriores que no contengan huella de auxiliar conservan la comparación de saldo y de movimientos del propio día; una nueva versión incorpora la comprobación completa del historial.

No se incorporan importadores de Excel, sincronización con bancos/AirPak, conciliación de extractos, reapertura de cierres financieros ni una compensación general entre cuentas. Esos límites no se presentan como procesos implementados.

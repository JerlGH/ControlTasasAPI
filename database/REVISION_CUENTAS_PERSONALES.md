# Segunda revisión de cuentas personales y auxiliares

**Actualización posterior a esta revisión:** los tres procesos seleccionados por el usuario ya están implementados: depósitos/reintegros de fondos, retiros que generan deuda del titular y préstamos entregados a particulares vinculados a CxC. El contrato y las comprobaciones actuales están en [API_CUENTAS_PERSONALES.md](API_CUENTAS_PERSONALES.md). Este informe conserva la evidencia del estado anterior; los hallazgos sobre reducción de obligaciones automáticas, partidas de apertura y compensación general no se incluyen en esa entrega.

Revisión del 5 de noviembre de 2026, posterior a la primera ampliación administrativa. La revisión contrasta el Excel con las migraciones, servicios y rutas actuales. No modifica la implementación, las migraciones ni una base de datos real.

**Corrección posterior:** la [segunda revisión del Libro Diario](REVISION_LIBRO_DIARIO.md) resolvió el caso de reducción manual frente a obligación automática descrito aquí. Ahora se puede imputar un abono manual al cargo automático y se impiden pagos superiores al saldo neto. Las evidencias de rechazo/aceptación que siguen corresponden al estado histórico anterior.

## 1. Resultado

**El control está parcialmente cubierto.** La estructura permite identificar personas, crear varias cuentas, registrar cargos/abonos, calcular saldos y aplicar cobros o pagos parciales. No resuelve todavía todos los movimientos de una cuenta personal como la del Excel: depósitos del titular, retiros, nuevos préstamos, reintegros, cruces de saldo y ajustes aplicados a obligaciones generadas automáticamente.

La primera ampliación construyó el auxiliar y la aplicación de pagos. Para administrar completamente las cuentas personales hace falta completar el proceso que vincula cada movimiento con el dinero y con las partidas que modifica.

## 2. Evidencia del Excel

Se verificó que el archivo original sigue siendo el analizado: SHA-256 `8e2189a01a4251fa0ae1dd09ef879b3bba0daf4b726b93116a57ea372efeaf87`. Se reutilizó la extracción de sus valores y fórmulas después de comprobar esa identidad. No se actualizaron vínculos externos ni se editó el libro.

| Auxiliar | Celdas verificadas | Funcionamiento |
| --- | --- | --- |
| MAMA | B2:D2, D32:D39 | Saldo anterior + entra − sale. El saldo pasa de positivo a negativo, se cancela y vuelve a positivo. |
| MAMA | D32 = 1588.625; D33 = −4911.375; D37 = −37621.375; D38 = 0; D39 = 200000 | La misma cuenta conserva depósitos del titular y, después de consumirlos, importes que debe reintegrar. Son valores según el orden de filas del auxiliar; hay fechas fuera de orden que se deben revisar al migrar. |
| Pendientes COR | F14 = MAMA!D88 = 25910 | El saldo de la cuenta personal alimenta una obligación del control global. |
| C x C CORD | M3:Q3, P17:P20 | Las salidas/cargos aumentan lo que una persona debe y los abonos lo reducen. Incluye cargos acumulados y pagos parciales. |
| C x C CORD | N36 = 50 × 36.64 = 1832 | Hay pagos expresados originalmente en USD que se aplican a un auxiliar en NIO. |
| C x P USD | J37:N38; M38 = −7000 | El auxiliar del Excel registra esa deuda con signo negativo; su orientación difiere de la convención de deuda positiva de la API. |

El comportamiento de MAMA corresponde a una cuenta corriente personal: una misma identidad puede representar dinero del titular que mantiene el negocio o dinero adelantado por el negocio. No basta con interpretar todos sus registros como cancelaciones de una deuda fija.

## 3. Comparación con lo implementado

| Necesidad | Estado actual | Observación |
| --- | --- | --- |
| Identificar particulares sin convertirlos en clientes o usuarios | Cubierta | `contrapartes_financieras` es un catálogo independiente. |
| Varias cuentas por persona y moneda | Cubierta | Una contraparte puede tener varias cuentas NIO/USD; no se suman monedas directamente. |
| Registrar deuda inicial | Parcial | `saldo_inicial` entra al saldo, pero no crea una partida cancelable. |
| Aumentar una deuda sin movimiento de dinero | Cubierta en el auxiliar | Un cargo manual aumenta el saldo. Requiere motivo y clave. |
| Reducir una deuda sin movimiento de dinero | Parcial | El abono manual reduce el saldo general; debe imputarse a la partida. Los cargos automáticos no admiten esa imputación manual. |
| Cobrar parcialmente una CxC | Cubierta para cargos identificados | Un ingreso financiero y una aplicación a su cargo reducen la deuda. |
| Pagar parcialmente una CxP | Cubierta para cargos identificados | Un egreso y una aplicación reducen la deuda. |
| Abonar en moneda distinta a la de la deuda | Cubierta en las aplicaciones financieras | Conserva importe de origen, tasa e importe aplicado. |
| Recibir un depósito de un particular para aumentar su fondo | Incompleta | La aplicación a cargos de `fondo` exige egreso; un ingreso se rechaza. |
| Retirar de un fondo hasta lo pendiente de su cargo | Cubierta con condiciones | El retiro se aplica como egreso contra un cargo ya identificado. |
| Retirar más que el saldo y convertir el excedente en deuda del titular | Incompleta | La aplicación no puede superar el cargo pendiente. Un ajuste manual puede dejar saldo negativo, pero no completa el vínculo con el retiro real. |
| Recibir el reintegro de una cuenta `fondo` negativa | Incompleta | La aplicación mantiene la dirección de egreso del tipo fondo y rechaza el ingreso de reintegro. |
| Entregar un nuevo préstamo a una persona y aumentar CxC | Incompleta | La aplicación a un cargo CxC exige ingreso. La salida original y el cargo deben capturarse separadamente, sin un proceso general que los vincule. |
| Aumentar una deuda existente por un nuevo desembolso recibido | Parcial | Hay desembolso en financiamientos nuevos; no es un proceso general de adición a cualquier cuenta existente. |
| Compensar cuentas auxiliares de una misma persona | Incompleta | Las aplicaciones manuales exigen la misma cuenta. Hay compensación laboral específica; no una compensación personal general entre cuentas. |
| Consultar saldo y movimientos de una cuenta a una fecha | Cubierta | `cuentas/{id}/saldo?fecha=...` calcula saldo y movimientos acumulados. |
| Consultar estado conjunto por persona | Parcial | Se pueden listar sus cuentas por contraparte y consultar cada saldo. No hay estado conjunto nativo por persona/moneda. |
| Anular, auditar y conservar cierres | Cubierta para los procesos existentes | Se anulan registros con controles de dependencias y fecha; no se borran sus evidencias. |

## 4. Hallazgos comprobados

### 4.1 Reducciones que no actualizan el pendiente del documento

Es el problema prioritario de consistencia. Se reprodujo este flujo con la API real del proyecto contra SQLite en memoria:

1. Crear un gasto por pagar de NIO 100, asociado a una CxP. Se genera su cargo automático.
2. Registrar un abono de ajuste de NIO 20 en esa cuenta, con motivo.
3. El saldo general de la cuenta queda en **80**.
4. El pendiente del gasto sigue siendo **100**.
5. Intentar aplicar el ajuste al cargo automático mediante `aplicacionescuenta` devuelve **422**: los cargos automáticos deben administrarse desde su documento de origen.
6. Aplicar al gasto un pago de **100** devuelve **201**; la cuenta termina en **−20**.

El saldo negativo puede tener un significado legítimo si se autorizó un anticipo o saldo a favor. El defecto aquí es que la reducción no modifica la partida correspondiente: la aplicación acepta el importe bruto porque sigue viendo un pendiente de 100, sin identificar los 20 adicionales como un hecho distinto.

Los cargos manuales sí admiten imputar el abono mediante `aplicacionescuenta`. Esa separación funciona, pero no debe confundirse «saldo reducido» con «deuda individual reducida» mientras el abono siga sin aplicar.

Corrección necesaria: permitir ajustes documentados contra una obligación y actualizar en una sola transacción el saldo de la cuenta y el pendiente de la partida. Cuando corresponda, el ajuste debe tener también su clasificación de gasto/ingreso; una reducción de deuda no siempre reduce el gasto original.

### 4.2 Los fondos personales no tienen todos sus movimientos de dinero

`AplicacionFinancieraService` utiliza ingreso para cargos CxC/recargas y egreso para cargos CxP/fondo. Esa regla sirve para cobrar o pagar una obligación. No cubre ambas direcciones de una cuenta corriente personal.

Pruebas:

- Ingreso NIO 100 para un cargo de fondo NIO 100: **422**, dirección incompatible.
- Egreso NIO 40 aplicado a ese cargo: **201**; saldo 60.
- Otro retiro NIO 80: **422**, supera el pendiente del cargo.
- Abono manual NIO 80: **201**; el saldo general puede quedar en −20.
- Ingreso NIO 20 para reintegrar ese fondo negativo: **422**, dirección incompatible.

El cálculo numérico sí admite saldos negativos. Lo que falta es administrar el dinero que origina y cancela ese saldo con su significado correcto y sus vínculos.

### 4.3 Adiciones de deuda originadas por dinero entregado o recibido

Se comprobó que aplicar un egreso a un cargo CxC devuelve **422**. Es correcto para una operación de cobro, pero no hay una operación equivalente para «entregamos dinero a esta persona y ahora nos debe más».

Tampoco se puede vincular esa salida directamente mediante `movimientoscuenta.id_operacion`: el servicio rechaza asignar una operación a un ajuste manual. Por tanto, crear manualmente un cargo y registrar aparte el egreso reproduce el saldo, pero no deja resuelto el vínculo entre ambos ni evita por sí solo que ese dinero se clasifique en otro destino.

Los desembolsos de financiamiento y los anticipos laborales sí tienen enlaces específicos. Son soluciones de esos módulos; no sustituyen el proceso de préstamo/adición para cualquier particular y cuenta existente.

### 4.4 Apertura con saldo pero sin partidas

Una cuenta con `saldo_inicial: 500` devuelve saldo 500 y **cero movimientos**. Las aplicaciones financieras no admiten `id_cuenta` como destino: necesitan un cargo/documento identificado. No se puede cancelar directamente ese saldo agregado desde la aplicación de pagos.

Solución operativa actual: apertura cero y un cargo identificado por el saldo heredado, o desglosar sus partidas. **No registrar a la vez inicial 500 y cargo 500 por la misma deuda**, porque produciría saldo 1000. El proceso de apertura debería ofrecer esa conversión de forma controlada.

### 4.5 Signos, naturaleza y clasificación del capital

La API calcula `saldo = inicial + cargos − abonos`. En CxP, un saldo positivo representa deuda pendiente; el reporte de capital lo resta. En el auxiliar `C x P USD` del Excel, ciertas deudas se guardan con signo negativo. Copiar ese signo literalmente invertiría su contribución al capital.

Se debe mapear cada auxiliar según su naturaleza y fórmula. No usar valor absoluto indiscriminadamente: eliminaría los saldos a favor reales. Para `fondo`, el reporte actual sí resta el saldo positivo y agrega el equivalente de un saldo negativo, porque invierte su contribución al pendiente neto.

Las cuentas nuevas quedan en `rubro_capital: excluido` por defecto. Para integrarlas hay que clasificarlas expresamente como pendiente, deuda o banco según corresponda. Esa clasificación debe definirse antes de registrar movimientos; las dependencias restringen cambios financieros posteriores.

## 5. Qué completar en el proyecto

La estructura actual puede conservarse como base. No es necesario modificar los maestros protegidos.

1. **Unificar ajustes y pendientes.** Registrar aumentos/reducciones contra partidas manuales o automáticas, con importe, causa, fecha, documento y efecto económico. Corregir primero el caso del gasto 100 reducido a 80.
2. **Dar comportamiento de cuenta corriente a las cuentas personales que lo requieren.** Conservar la misma cuenta cuando el saldo cambia de signo y expresar «fondos del titular» o «deuda del titular». Definir si se admite sobregiro y bajo qué condiciones.
3. **Vincular movimientos con dinero.** Depósito del titular, retiro, préstamo entregado, préstamo recibido, cobro y pago deben relacionar operación de caja/banco y movimiento auxiliar en una transacción, reservando el origen para evitar duplicaciones.
4. **Separar dinero de ajustes.** Una condonación, corrección o compensación no debe crear un ingreso/egreso ficticio. Los movimientos deben expresar su motivo de negocio, además de cargo/abono.
5. **Completar aperturas y saldos a favor.** Crear partidas de apertura administrables; identificar anticipos y excedentes, y permitir su devolución o aplicación futura sin perder el vínculo.
6. **Agregar compensación y estado por persona.** Mantener las distintas cuentas, contratos y monedas; permitir cruces explícitos y consultar saldos por cobrar/pagar por moneda, con sus movimientos.
7. **Verificar los nuevos casos completos.** Depósito, retiro parcial, retiro excedido cuando esté permitido, reintegro, nuevo préstamo, adición/reducción sin dinero, ajuste de obligación automática, devolución y anulación de cada origen.

No se recomienda resolverlo cambiando manualmente el tipo CxC/CxP cada vez que el saldo cruce cero: las reglas de aplicación y el historial deben seguir siendo coherentes con la misma cuenta.

## 6. Condiciones actuales de operación

- La API administrativa requiere administrador activo. Un cajero no administra estas cuentas directamente con las rutas actuales.
- Vincular una operación de caja como origen exige que la jornada esté cerrada. No hay actualización inmediata de la cuenta personal mientras el cajero mantiene abierta esa jornada.
- La API permite administrar registros ya existentes, pero no importa las cuentas de los Excel.
- Los ajustes posteriores a un cierre financiero se capturan en un periodo abierto; no se deben alterar los cierres históricos para corregir estos hallazgos.

## 7. Verificación y referencias de código

Se ejecutó la regresión de API existente: **619 comprobaciones de caja y 605 administrativas**, todas aprobadas. Además, se probaron los escenarios de esta revisión en la misma base SQLite en memoria, utilizando personas/cuentas ficticias y los controladores reales. Los rechazos y la divergencia de saldo/pendiente descritos se reprodujeron; no son funciones corregidas durante esta revisión. Las pruebas anteriores no cubrían estos flujos completos de cuenta personal.

No se leyó `.env`, no se ejecutó Artisan y no se conectó una base real. El único archivo incorporado al proyecto por esta revisión es este informe; la implementación queda intacta.

Referencias:

- [Migración de contrapartes, cuentas, movimientos y aplicaciones](migrations/2026_11_05_000000_administracion_cuentas.php).
- [Cálculo de saldos y cruce de partidas](../app/Services/Administracion/LedgerService.php), métodos `balance`, `pending`, `apply` y `prepareOperation`.
- [Reglas de dirección y pendiente de aplicaciones](../app/Services/Administracion/AplicacionFinancieraService.php), métodos `pending`, `prepare` y `post`.
- [Validación de ajustes y restricciones de cargos automáticos](../app/Services/Administracion/AdministracionService.php), `prepare`, ramas `movimientoscuenta` y `aplicacionescuenta`.
- [Contribución de cada naturaleza al capital](../app/Services/Administracion/CapitalService.php), método `calculate`.
- [Contrato de la ampliación implementada](API_ADMINISTRACION.md).

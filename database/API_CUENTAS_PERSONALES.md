# Depósitos, retiros y préstamos de particulares

La entrega unificada para el desarrollo C# está en [INTEGRACION_API_CSHARP_V4.md](INTEGRACION_API_CSHARP_V4.md); incluye este contrato, los demás módulos y las diferencias respecto a la documentación anterior.

Ampliación del 5 de noviembre de 2026. Complementa [API_ADMINISTRACION.md](API_ADMINISTRACION.md) y la [integración C#](INTEGRACION_API_CAJA_CSHARP.md). Los tres procesos usan el nuevo recurso **`/api/administracion/movimientospersonales`**, con el mismo token Sanctum y permiso de administrador activo.

## 1. Cuenta, dinero y movimiento personal

La cuenta financiera debe tener `id_contraparte` vigente. Depósitos y retiros utilizan `tipo: fondo`; los préstamos entregados, `tipo: por_cobrar`. Crear la cuenta y su titular con las rutas administrativas existentes. Una misma contraparte puede tener varios fondos o CxC, separados por contrato y moneda.

El dinero se representa mediante una `operacion` financiera existente:

- Depósito del titular: operación `ingreso`.
- Retiro del titular: operación `egreso`.
- Préstamo que entregamos a una persona: operación `egreso`.

La operación puede provenir de banco/POS o reconocer una entrada/salida ya registrada en una jornada cerrada de caja, siguiendo las reglas del módulo administrativo. Un traslado entre fondos (`transferencia`) no sirve como origen de estos movimientos.

Primero se registra/reconoce la operación financiera y se conserva su ID. Después se registra el movimiento personal contra ella. Son dos peticiones: la operación bancaria ya mueve su cuenta; el movimiento personal actualiza únicamente el auxiliar del particular. Si falla esta segunda captura, se debe consultar/reutilizar la operación original, no volver a registrar el ingreso/egreso.

El alta personal y su movimiento auxiliar se guardan en una misma transacción. Su importe ocupa parte del dinero disponible de la operación, compartiendo el límite con las aplicaciones financieras existentes. Se pueden distribuir importes de una operación entre varios destinos, pero nunca superar el total. Una operación reservada para depósito de matriz, anticipo laboral o desembolso de financiamiento no puede reutilizarse.

## 2. Rutas y captura

| Método | Ruta relativa a `/api/administracion` | Uso |
| --- | --- | --- |
| GET | `movimientospersonales` | Lista paginada; filtros `id_cuenta`, `id_operacion`, `tipo`, `estado`, `moneda` y fechas, entre otros del contrato común. |
| GET | `movimientospersonales/{id}` | Consulta del movimiento y sus resultados guardados. |
| POST | `movimientospersonales` | Depósito, retiro o préstamo entregado. |
| PATCH/PUT | `movimientospersonales/{id}` | Solo metadatos admitidos, como concepto, observación y documento. Los datos financieros son inmutables. |
| DELETE | `movimientospersonales/{id}` | Anula la clasificación con `motivo`; conserva la evidencia. |
| GET | `cuentas/{id}/saldo?fecha=YYYY-MM-DD` | Saldo al corte, movimientos y posición del titular para fondos. |

POST devuelve HTTP 201, `{"Registro":{...},"Status":201}`. Detalle/actualización/anulación usan `Registro`; listados usan `Registros` y `Paginacion`. La consulta de saldo utiliza `Resultado`.

| Campo POST | Requerido | Significado |
| --- | --- | --- |
| `clave` | Sí | Identidad única de la captura, hasta 100 caracteres. Se conserva incluso después de anular. |
| `id_cuenta` | Sí | Cuenta del particular, con contraparte identificada. |
| `id_operacion` | Sí | Dinero ya recibido/entregado, vigente y disponible. |
| `fecha` | Sí | Fecha de efecto `YYYY-MM-DD`, no anterior al dinero ni a la apertura de la cuenta. |
| `tipo` | Sí | `deposito`, `retiro` o `prestamo_entregado`. |
| `monto` | Sí | Parte aplicada del dinero, en la moneda de la operación. Positivo, hasta 8 decimales. |
| `tasa_cambio` | Si difieren las monedas | NIO por USD, positiva, hasta 6 decimales. |
| `concepto` | Sí | Descripción, hasta 1000 caracteres. |
| `id_documento` | No | Documento vigente registrado en el módulo administrativo. |
| `observacion` | No | Texto adicional, hasta 10000 caracteres. |

Los importes se envían como cadenas decimales con punto. Se utiliza conversión decimal `HALF_UP` a 8 decimales. El equivalente debe caber en `DECIMAL(24,8)`. Los campos calculados no se envían: `moneda_origen`, `moneda`, `monto_cuenta`, saldos, desgloses, movimiento generado, autor y estado los determina el servidor.

Una clave repetida devuelve 409. Si se intenta usar una operación sin importe disponible, una naturaleza incompatible o una fecha no admitida, devuelve 422. Tras perder una respuesta, consultar los registros por la operación conocida y comprobar la clave antes de repetir el alta.

## 3. Depósito y reintegro del titular

Ejemplo: la operación 81 representa un ingreso NIO 100 ya registrado; la cuenta 24 es el fondo del titular.

```json
{
  "clave": "PERSONA-24-DEPOSITO-001",
  "id_cuenta": 24,
  "id_operacion": 81,
  "fecha": "2026-11-10",
  "tipo": "deposito",
  "monto": "100.00",
  "concepto": "Dinero recibido del titular"
}
```

El movimiento genera un **cargo** en el fondo. Si el saldo era positivo/cero, aumenta los fondos del titular. Si era negativo, cancela primero su deuda y el remanente vuelve a ser fondos del titular.

| Saldo anterior | Depósito | Deuda cancelada | Fondos incrementados | Saldo posterior |
| --- | --- | --- | --- | --- |
| 0 | 100 | 0 | 100 | 100 |
| −50 | 20 | 20 | 0 | −30 |
| −30 | 50 | 30 | 20 | 20 |

No se necesita cambiar la cuenta a CxC para recibir el reintegro de un fondo negativo. También se reconocen fondos y deudas existentes en el `saldo_inicial`, sin volver a crearlos como cargos.

## 4. Retiro y deuda del titular

El cuerpo es igual, con `tipo: retiro` y una operación de egreso. El movimiento genera un **abono** en el fondo. Consume primero el saldo positivo; el importe que lo exceda se convierte en deuda del titular, expresada como saldo negativo de esa misma cuenta.

| Saldo anterior | Retiro | Fondos utilizados | Deuda generada | Saldo posterior |
| --- | --- | --- | --- | --- |
| 100 | 40 | 40 | 0 | 60 |
| 100 | 150 | 100 | 50 | −50 |
| −50 | 20 | 0 | 20 | −70 |

Este proceso permite el excedente solicitado: no impone un límite de crédito adicional. El permiso de administrador y la evidencia del egreso identifican la operación autorizada.

## 5. Préstamo entregado y cobro posterior

Crear/reutilizar una cuenta **por cobrar** del particular. La cuenta debe tener saldo neto no negativo antes de entregar el préstamo; si existe un saldo agregado a favor del titular, debe regularizarse primero.

```json
{
  "clave": "PERSONA-24-PRESTAMO-001",
  "id_cuenta": 31,
  "id_operacion": 82,
  "fecha": "2026-11-10",
  "tipo": "prestamo_entregado",
  "monto": "300.00",
  "concepto": "Entrega de préstamo al particular"
}
```

La operación 82 es un egreso real de 300. El servidor crea un cargo en CxC por el equivalente y devuelve su **`id_movimiento_generado`**. Puede haber varios préstamos en la misma cuenta, cada uno con su cargo y origen.

Para cobrar, registrar un ingreso financiero y utilizar la API existente de aplicaciones:

```json
{
  "id_operacion": 83,
  "fecha": "2026-11-11",
  "monto": "120.00",
  "id_cargo": 205
}
```

POST a `/api/administracion/aplicaciones`. El valor 205 se sustituye por el movimiento generado del préstamo. Un cobro parcial de 120 deja pendiente 180 de un préstamo de 300. Se admite otra moneda con tasa y equivalente. Se comprueban el pendiente del cargo y la deuda neta de la cuenta para evitar cobrar por encima de lo debido. No se generan intereses, calendarios ni gastos automáticamente al entregar este préstamo.

En `aplicaciones`, la excepción de usar `id_cargo` para un cargo automático queda limitada al préstamo personal vigente; gastos y nómina siguen usando su destino específico. Para reducciones sin dinero, la revisión posterior permite cruzar un abono manual con un cargo automático de la misma cuenta mediante `aplicacionescuenta`. No se permite administrar desde allí un abono automático.

## 6. Resultados, saldo y capital

Cada registro conserva:

- `monto` y `moneda_origen`: dinero utilizado de la operación.
- `monto_cuenta` y `moneda`: equivalente en la moneda del auxiliar.
- `saldo_anterior` y `saldo_posterior`: saldo de la cuenta al registrar la captura.
- `fondos_utilizados`, `fondos_incrementados`, `deuda_generada` y `deuda_cancelada`: desglose del efecto de ese movimiento.
- `id_movimiento_generado`: cargo/abono automático enlazado con la operación financiera.

Los desgloses de un registro describen el momento de su captura. Para la posición actual o a otra fecha, consultar el saldo de la cuenta. En fondos, `Resultado.posicion_personal` contiene `fondos_del_titular` y `deuda_del_titular`, ambos no negativos:

```json
{
  "saldo": "-50.00000000",
  "posicion_personal": {
    "fondos_del_titular": "0.00000000",
    "deuda_del_titular": "50.00000000"
  }
}
```

El capital utiliza el saldo del auxiliar existente, sin sumar nuevamente el préstamo ni el movimiento personal. Con `rubro_capital: pendiente`, un fondo positivo resta como obligación y uno negativo agrega una cuenta por recuperar; una CxC positiva agrega su saldo por cobrar. La clasificación de la cuenta debe definirse al crearla.

## 7. Correcciones y cierres

Los importes, tipo, cuenta, operación, fecha y tasa no se editan después de contabilizar. La corrección consiste en anular y reclasificar en periodo abierto con otra clave. La anulación revierte el movimiento auxiliar y libera el importe de la operación, pero **no devuelve dinero ni anula el movimiento bancario o de caja**. Una devolución efectiva requiere su operación real.

No se puede anular el préstamo mientras tenga cobros/aplicaciones activos. Tampoco se puede retirar o modificar una entrada anterior si alteraría el saldo anterior guardado por un movimiento personal posterior. Los movimientos personales se registran en orden cronológico de su cuenta; los del mismo día se ordenan por su registro. Para corregir antecedentes en un periodo abierto, resolver primero los movimientos personales posteriores.

Los cierres financieros mantienen su protección: no se anulan ni registran efectos en una fecha ya cerrada. Un movimiento del día siguiente no cambia los saldos ni las fotografías de cierres anteriores.

## 8. Esquema y comprobaciones

Se agrega [la migración de movimientos personales](migrations/2026_11_05_000003_movimientos_personales.php), [MovimientoPersonal](../app/Models/Administracion/MovimientoPersonal.php) y [CuentaPersonalService](../app/Services/Administracion/CuentaPersonalService.php). El controlador administrativo común y sus rutas dinámicas exponen el nuevo recurso; los límites compartidos y guardas de origen se integran en los servicios existentes.

```powershell
php tests/Schema/caja_migrations.php
php tests/Schema/cuentas_personales_api.php
```

La segunda prueba incluye las regresiones de caja y administración. Comprueba depósitos, retiros excedidos, reintegros, apertura positiva/negativa de fondos, préstamos y cobros parciales, moneda distinta, límites compartidos de dinero, vínculos de caja, anulaciones, permisos, cronología e integración con capital/cierre. Se utiliza SQLite en memoria y el esquema MySQL se compila sin conectar a un servidor.

No se modifican los maestros ni las migraciones protegidas, y no se aplicaron migraciones en una base real. Esta entrega completa los tres procesos solicitados. La [segunda revisión del Libro Diario](REVISION_LIBRO_DIARIO.md) corrigió posteriormente el pago excesivo frente a reducciones manuales y habilitó imputarlas a cargos automáticos. La compensación general entre cuentas sigue siendo un proceso distinto.

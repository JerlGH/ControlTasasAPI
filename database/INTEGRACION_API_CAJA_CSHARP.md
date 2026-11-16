# Integración de ControlTasasAPI con el sistema de escritorio C#

**Entrega unificada V4:** la terminal LAFISE/BANPRO, comisiones, reportes, capital y este contrato individual están incorporados en [INTEGRACION_API_CSHARP_V4.md](INTEGRACION_API_CSHARP_V4.md).

**Referencia principal actualizada:** entregar V4 al chat de desarrollo. Integra todos los módulos, el comparativo detallado de campos anteriores, DTOs, ejemplos y limitaciones conocidas. Este archivo se conserva como referencia individual anterior.

**Contrato revisado el 4 de noviembre de 2026.** Documento para el proyecto y chat «Ggrupo Systems - Control de Caja Wester - Sistema escritorio».

**Ampliación administrativa del 5 de noviembre:** consultar [API_ADMINISTRACION.md](API_ADMINISTRACION.md) para consumir consolidación madre, conciliación/liquidación de matriz, cuentas, gastos, obligaciones laborales, comisiones, obras, activos, financiamientos e incidencias. Usa el mismo token y el prefijo `/api/administracion`, con permiso de administrador y respuestas `Registro`, `Registros` o `Resultado`. El contrato individual de esta guía sigue vigente.

**Cuentas personales:** [API_CUENTAS_PERSONALES.md](API_CUENTAS_PERSONALES.md) documenta `movimientospersonales`: depósitos, retiros con generación de deuda, reintegros y préstamos entregados con cobros parciales. El nuevo recurso conserva las dos monedas/importes, la operación de origen y sus movimientos auxiliares; no se vuelven a mover los importes físicos de la jornada.

Esta guía describe el código actual del backend Laravel y permite implementar el cliente sin disponer de las conversaciones anteriores. Los ejemplos usan datos ficticios. Los identificadores deben sustituirse por los devueltos por la API; no son identificadores reservados.

Accesos rápidos: [autenticación](#2-dirección-base-autenticación-y-permisos), [tipos JSON](#3-tipos-de-datos-y-reglas-de-serialización), [rutas y respuestas](#4-inventario-de-rutas-y-claves-de-respuesta), [apertura](#5-modelo-de-jornada-y-campos-del-arqueo), [cheques](#7-cheques-operación-e-inventario-documental), [conteo](#9-conteo-físico-por-denominaciones), [cierre](#10-resumen-y-cierre), [errores](#15-errores-conflictos-y-repetición-de-peticiones), [cliente C#](#16-cliente-c-de-referencia), [ejemplo completo](#17-ejemplo-c-de-un-ciclo-completo).

## 1. Alcance y condiciones de integración

El módulo representa la jornada de un cajero mediante un **arqueo**. A ese arqueo se vinculan los movimientos, operaciones de cambio, cheques, conteos de efectivo y registros auxiliares del día. El backend calcula el resumen y guarda el cierre.

La implementación reproduce los registros base del Excel individual de caja «Eveling», incluida la continuidad de apertura de la macro `AirPak1`. También permite registrar los envíos de los dos formatos DHL y los enrolamientos. La consolidación entre trabajadores y los nuevos procesos administrativos se describen en el contrato complementario enlazado arriba. La importación automática de archivos sigue pendiente.

Las migraciones y datos existentes de `users`, `trabajadores`, `subagencias`, `clientes` y `mesas_cambio` están protegidos. Los nuevos registros pueden referenciar sus identificadores existentes. Registrar un enrolamiento no crea un cliente, y vincular una operación de caja a una mesa no modifica la mesa.

**Condición de despliegue:** los cambios están preparados en el repositorio; no se han aplicado a la base real durante este trabajo. Si ya se ejecutó una versión anterior de las migraciones de expansión, modificar sus archivos `create` no actualiza esa base: hará falta preparar y aplicar migraciones incrementales según su estado real. Esta guía no confirma que un servidor publicado ya tenga esta versión.

No existe una ruta de versión del contrato. Conviene coordinar la actualización del backend y del escritorio, y conservar esta fecha de revisión con la versión del cliente.

## 2. Dirección base, autenticación y permisos

### 2.1 Transporte

La URL base es configurable y termina en `/api/`. Ejemplos ilustrativos:

```text
Desarrollo: http://localhost:8000/api/
Servidor:   https://servidor.example/api/
```

No se proporciona aquí una dirección de producción real. En C#, las rutas relativas deben escribirse sin `/` inicial: `arqueos/123/resumen`. Un `/` inicial puede descartar `/api/` al combinarse con `HttpClient.BaseAddress`.

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <access_token>
```

`Content-Type` corresponde a peticiones con cuerpo. `Accept` debe enviarse también en consultas y errores para solicitar JSON. Los cuerpos se codifican en UTF-8.

### 2.2 Inicio de sesión

`POST /api/login`, sin token:

```json
{
  "usuario": "cajero_demo",
  "contraseña": "CLAVE_DE_EJEMPLO"
}
```

La clave JSON es exactamente `contraseña`, con ñ. Respuesta HTTP 200, abreviada en los datos del usuario:

```json
{
  "usuario": {
    "id": 7,
    "usuario": "cajero_demo",
    "rol_usuario": "Cajero",
    "estado_usuario": "Activo",
    "id_trabajador": 12
  },
  "access_token": "TOKEN_DEVUELTO_POR_SANCTUM",
  "token_type": "Bearer",
  "Status": 200
}
```

Conservar el token para las siguientes peticiones. El backend actual no devuelve `expires_in` ni expone una ruta de renovación de token. La vigencia depende de la configuración del servidor. Un 401 en una petición protegida requiere recuperar la sesión mediante el flujo de autenticación del escritorio.

Particularidades del login existente: campos ausentes producen 400 con `Mensaje` y `Errores`; contraseña incorrecta para un usuario existente produce 401; un nombre de usuario inexistente puede producir 404 porque el controlador usa `firstOrFail`. El cliente debe manejar las tres situaciones. El módulo de caja comprueba además que el usuario esté activo; un token válido de un usuario inactivo recibe 403 al acceder a caja.

`POST /api/logout`, autenticado y sin cuerpo, devuelve 200 con `Mensaje`, `Status` textual y `response_code`. **El controlador actual revoca todos los tokens del usuario**, lo cual puede cerrar sus otras sesiones. No deserializar ese `Status` como entero usando el DTO de arqueos.

### 2.3 Alcance de los datos

| Perfil | Lecturas y escrituras del módulo |
| --- | --- |
| Cajero activo | Consulta sus jornadas y registros; captura en sus jornadas abiertas. Consulta las cajas de su agencia, los precios de su agencia/globales y el catálogo de servicios. |
| Administrador activo | Consulta todas las jornadas; puede capturar en representación de un cajero y administrar cajas, servicios y precios. También realiza las vinculaciones supervisadas entre movimientos. |

El rol administrativo reconocido es `Administrador`, ignorando mayúsculas y espacios extremos. La agencia del cajero se obtiene de su trabajador asociado. La jornada conserva su propia agencia histórica.

Los filtros `id_usuario` o `id_arqueo` nunca conceden acceso a registros de terceros. Habitualmente un registro fuera del alcance devuelve 404; determinadas acciones explícitas devuelven 403 o 422. No interpretar un 404 como permiso para crear una copia del registro.

Las rutas existentes de alta, modificación y eliminación de usuarios requieren administrador. Esta guía no redefine los permisos ni los contratos de todos los módulos históricos.

## 3. Tipos de datos y reglas de serialización

| Dato | JSON de captura | Tipo sugerido en C# | Observaciones |
| --- | --- | --- | --- |
| Identificador | `123` | `long` / `long?` | No enviar objetos completos donde se espera una FK. |
| Importe | `"1000.25"` | `decimal` / `decimal?` | Respuestas monetarias de modelos: cadenas decimales. |
| Tasa | `"36.500000"` | `decimal` / `decimal?` | Hasta 6 decimales; positiva. |
| Cantidad de piezas o servicios | `10` | `uint` o `long` validado | Rango permitido habitual: 0 a 4294967295. |
| Fecha de negocio | `"2026-11-04"` | Cadena validada o tipo de fecha con conversor | Formato `yyyy-MM-dd`, sin conversión horaria. |
| Fecha y hora de captura | `"2026-11-04 09:30:00"` | Cadena validada o conversor específico | Véase la nota de zona horaria. |
| Confirmación | `true` | `bool` | Usar booleanos JSON. |
| Código, documento, guía, cuenta, teléfono | `"001234"` | `string` | Preservar ceros iniciales y caracteres originales. |

Los importes de dos decimales admiten hasta 16 dígitos enteros; las tasas, hasta 6 enteros y 6 decimales. `monto_cordobas` de una operación de cambio admite hasta 16 enteros y 8 decimales. El peso de un detalle DHL admite 9 enteros y 3 decimales.

Enviar decimales con punto, sin agrupación de miles, símbolo de moneda, espacios ni notación científica. `1.234,50` y `1,234.50` no son formatos de captura válidos. Usar `CultureInfo.InvariantCulture` para el transporte y la cultura local únicamente para la presentación. Evitar `double` y `float` en cálculos monetarios.

**Ausente, null y cero tienen significados distintos:**

- Omitir un campo en PATCH conserva su valor actual.
- Enviar `null` borra o deja pendiente un valor solamente si el campo admite null.
- Enviar `"0.00"` declara un importe cero. No debe usarse para sustituir un importe desconocido.
- Una fila de conteo de servicio con `cantidad: 0` significa informado sin operaciones; una fila ausente significa no informado.

No serializar automáticamente todas las propiedades null de un DTO de respuesta para construir una actualización. Usar DTOs de petición o un diccionario que contenga solo los campos que el usuario decidió modificar. A la inversa, un serializador que omite todos los null no puede expresar una limpieza intencional: en ese caso enviar la clave con null mediante un diccionario.

Los campos de fecha con cast explícito se devuelven como `yyyy-MM-dd`; los de fecha/hora de este módulo como `yyyy-MM-dd HH:mm:ss`. `created_at` y `updated_at` usan la serialización habitual de Laravel y pueden aparecer en ISO 8601. No asumir que todos los timestamps tienen el mismo formato ni que un timestamp sin sufijo representa UTC. La aplicación usa `APP_TIMEZONE`, cuyo valor predeterminado en el código es `UTC`; verificar la configuración del servidor. La fecha operativa la selecciona el cliente, no se deduce de `created_at`.

Las monedas canónicas son `NIO` y `USD`. La captura también normaliza `C$`/`CORDOBAS` a NIO y `$`/`US$`/`U$` a USD. Los filtros de consulta se aplican por igualdad y no realizan esa normalización: usar los valores canónicos. `Compra`, `Venta`, `Entrada` y `Salida` son los valores de tipo del módulo; los estados, categorías y medios se envían con las minúsculas indicadas en esta guía. El `tipo` de DHL conserva el texto original.

## 4. Inventario de rutas y claves de respuesta

Todas las rutas de la tabla tienen prefijo `/api`. Salvo historial, cada recurso admite:

```text
GET    /recurso             lista paginada
GET    /recurso/{id}        registro individual
POST   /recurso             creación
PUT    /recurso/{id}        actualización
PATCH  /recurso/{id}        actualización parcial recomendada
DELETE /recurso/{id}        anulación, inactivación o eliminación según recurso
```

| Recurso | Clave individual / POST / actualización | Clave de lista | DELETE |
| --- | --- | --- | --- |
| `arqueos` | `Arqueo` | `Arqueos` | Anula una jornada abierta; devuelve `Arqueo`. |
| `cajas` | `Caja` | `Cajas` | Inactiva. Administrador. |
| `detallearqueo` | `DetalleArqueo` | `DetallesArqueo` | Elimina el conteo de una moneda. |
| `cheques` | `Cheque` | `Cheques` | Anula si las custodias lo permiten. |
| `custodiascheques` | `Custodia` | `Custodias` | Elimina el detalle permitido de inventario. |
| `pases` | `Pase` | `Pases` | Anula. |
| `entradasysalidas` | `Entrada salida` | `Entradas y salidas` | Anula. |
| `tasascambioarqueo` | `Tasa` | `Tasas` | Elimina si no hay referencias que lo impidan. |
| `operacionescambiocaja` | `Operacion` | `Operaciones` | Anula. |
| `remesasbancarias` | `Remesa` | `Remesas` | Anula. |
| `precioproveedordolar` | `Precio proveedor dólar` | `Precios proveedor dólar` | Inactiva. Administrador. |
| `servicioscaja` | `Servicio` | `Servicios` | Inactiva. Administrador. |
| `conteosservicios` | `ConteoServicio` | `ConteosServicios` | Anula; no libera la combinación jornada/servicio. |
| `enrolamientos` | `Enrolamiento` | `Enrolamientos` | Anula. |
| `conteospapeleria` | `ConteoPapeleria` | `ConteosPapeleria` | Elimina. |
| `enviosdhl` | `Envio` | `Envios` | Anula. |
| `detallesenviosdhl` | `DetalleEnvio` | `DetallesEnvio` | Elimina si las referencias lo permiten. |
| `historialcaja` | `Historial` | `Historial` | Solo GET; no tiene rutas de escritura. |

**Las claves con espacios, acentos y mayúsculas son parte del contrato actual.** No reemplazar `Entrada salida` por `EntradaSalida`, ni `Operacion` por `Operación`, ni suponer una envoltura universal `data`.

### 4.1 Respuestas comunes

Creación HTTP 201, con el registro bajo la clave de la tabla:

```json
{
  "Tasa": {"id": 31, "id_arqueo": 123, "tipo": "Compra", "numero_precio": 1, "tasa": "36.500000"},
  "Status": 201
}
```

El registro también puede incluir timestamps y otras columnas del modelo. Las respuestas individuales y actualizaciones usan HTTP 200 y `Status: 200`.

Lista HTTP 200:

```json
{
  "Arqueos": [],
  "Paginacion": {"total": 0, "pagina": 1, "por_pagina": 50},
  "Status": 200
}
```

No es el paginador estándar `{data, links, meta}`. La lista vacía responde 200, mientras una búsqueda individual inexistente responde 404. Las listas tienen 50 filas por defecto, máximo 100. Aumentar `page` hasta cubrir `total`; no asumir que la primera respuesta contiene todo el día.

DELETE exige un cuerpo JSON, también en C#:

```json
{"motivo": "Registro duplicado durante la captura"}
```

Los DELETE genéricos devuelven HTTP 200 con `Mensaje` y `Status`, sin registro individual. El mensaje genérico puede decir «Registro anulado» incluso cuando el catálogo queda `inactivo`. Arqueos es la excepción: su DELETE devuelve el objeto `Arqueo` anulado. No se utilizan respuestas 204 en estos procesos.

PUT y PATCH actuales aceptan campos parciales. Para reducir ambigüedad se recomienda PATCH. La API valida y toma los campos definidos por cada controlador; algunos campos desconocidos se ignoran, otros están prohibidos expresamente. No confiar en que todos los errores de nombre de campo producirán 422.

### 4.2 Filtros y orden

| Consulta | Filtros disponibles |
| --- | --- |
| `/arqueos` | `id_usuario`, `id_subagencia`, `id_caja`, `fecha`, `fecha_desde`, `fecha_hasta`, `estado`, `page`, `per_page`. |
| Registros vinculados a jornadas | `id_arqueo`, `id_usuario`, `id_subagencia`, `fecha_desde`, `fecha_hasta`, `page`, `per_page`. El intervalo filtra la fecha de la jornada de origen. |
| Recursos genéricos | Además, por igualdad si existe la columna capturable: `moneda`, `tipo`, `categoria`, `estado`, `id_servicio`, `id_envio_dhl`, `momento`, `guia`, `numero_documento`. |
| `/conteospapeleria` | Campos propios `id_arqueo`, `id_usuario`, `id_subagencia`, `fecha_desde`, `fecha_hasta`, y filtros genéricos aplicables. |
| `/historialcaja` | `id_arqueo`, `entidad`, `id_registro`, `page`, `per_page`. |

Arqueos se ordena por fecha descendente y luego ID descendente. Las demás listas del módulo se ordenan por ID descendente. No hay búsqueda parcial, orden configurable ni filtro `estado_envio` implementado en el controlador genérico. Los filtros de claves como `id_servicio` también se aceptan como texto en la URL.

Ejemplos:

```text
GET /api/arqueos?fecha=2026-11-04&estado=abierto
GET /api/entradasysalidas?id_arqueo=123&categoria=gasto&per_page=100&page=1
GET /api/custodiascheques?id_arqueo=123&momento=cierre
GET /api/enviosdhl?id_arqueo=123&guia=0012345678
```

### 4.3 Rutas especializadas y compatibles

| Método y ruta | Respuesta / finalidad |
| --- | --- |
| `GET /arqueos/ultimo/{id_usuario}` | `Arqueo`, ordenado por fecha operativa; puede estar abierto, cerrado o anulado. 404 si no hay uno visible. |
| `GET /arqueos/detalle/{id_arqueo}` | `DetalleArqueo`: **array** de conteos de monedas, sin paginación. |
| `GET /arqueos/{id}/resumen` | `Arqueo`, `Resumen`, `Status`. Recalcula la vista del arqueo. |
| `GET /arqueos/{id}/registros` | `Arqueo` con relaciones anidadas, `Status`. |
| `POST /arqueos/{id}/cerrar` | `Arqueo`, `Status`. Cierra y guarda los resultados. |
| `GET /cheques/arqueo/{id_arqueo}` | `Cheques`: array sin paginación de cheques **originados** en ese arqueo. |
| `GET /pases/ultimos/{id_usuario}` | `Pases` y `Paginacion`; es una lista paginada, no un objeto ni un número fijo de pases. |
| `GET /precioproveedordolar/ultimo` | `Precio proveedor dólar`, `Status`; 404 sin precio visible vigente. Acepta `id_subagencia`. |

El precio «último» excluye futuros e inactivos, incluye los globales y ordena por vigencia/creación descendente. No cambia las tasas ni los resultados de las jornadas ya capturadas.

## 5. Modelo de jornada y campos del arqueo

### 5.1 Campos de apertura

| Campo | POST | Reglas |
| --- | --- | --- |
| `fecha` | Obligatorio | `yyyy-MM-dd`, posterior a todas las fechas registradas del usuario, incluso anuladas. |
| `id_usuario` | Opcional | Por defecto el autenticado. Otro usuario requiere administrador. |
| `id_subagencia` | Opcional | Por defecto la del trabajador del propietario. Debe existir; el cajero solo usa su agencia. |
| `id_caja` | Opcional/null | Caja activa de la agencia. Recomendable identificarla para las capturas nuevas. |
| `id_arqueo_anterior` | Opcional/null | Si se indica, debe ser el último cierre utilizable del mismo cajero. |
| `apertura_manual` | Opcional | Booleano; con una jornada previa requiere administrador. Es una opción de petición, no una columna de respuesta. |
| `caja_inicial_cordobas`, `caja_inicial_dolares` | Condicionales | Totales no negativos, **incluyen cheques**. Obligatorios en la primera apertura/manual. |
| `cheques_inicial_cordobas`, `cheques_inicial_dolares` | Opcionales/null | Principal de cheques contenido en el total inicial, nunca mayor que ese total. null = composición no conocida. |
| `tasa_referencia` | Opcional/null | Tasa positiva de hasta 6 decimales; no equivale a los cuatro precios de compra/venta. |
| `motivo_apertura_manual` | Condicional | Origen de los saldos; obligatorio en apertura manual. Hasta 10000 caracteres. |
| `observacion` | Opcional/null | Hasta 10000 caracteres. |

Los seis totales de plataforma también pueden informarse al abrir. Si no se conocen aún, omitirlos; se capturan durante el día.

Solo se admite una jornada abierta por usuario y una por caja identificada. Una fecha anulada sigue ocupada; no intentar reutilizarla. La API no implementa una apertura anterior a jornadas ya registradas.

### 5.2 Primera apertura

`POST /api/arqueos`:

```json
{
  "fecha": "2026-11-04",
  "id_caja": 1,
  "caja_inicial_cordobas": "1000.00",
  "caja_inicial_dolares": "100.00",
  "cheques_inicial_cordobas": "0.00",
  "cheques_inicial_dolares": "0.00",
  "tasa_referencia": "36.700000",
  "motivo_apertura_manual": "Saldos iniciales verificados para iniciar el sistema"
}
```

El cliente conserva `Arqueo.id` devuelto y lo utiliza en todos los registros de esa jornada. No confundirlo con `id_caja`, `id_usuario` o el número de día de una hoja Excel.

Si el total inicial NIO fuese `"1000.00"` y su componente de cheques `"300.00"`, el efectivo inicial conocido sería `"700.00"`. No enviar `"700.00"` como total inicial. El inventario documental de los 300 se captura por separado en custodias de apertura.

### 5.3 Apertura de un día posterior

Después de un cierre válido:

```json
{
  "fecha": "2026-11-05"
}
```

El servidor selecciona el último arqueo no anulado del usuario y exige que esté cerrado y pertenezca a la misma agencia. Copia:

- Los totales **contados** finales como saldos iniciales; no copia los esperados.
- El principal de cheques finales como componentes de cheques iniciales.
- Las identidades de los cheques de cierre a custodias de **apertura** de la nueva jornada.
- La caja, si no se indica otra activa válida, y la tasa de referencia disponible.

No copia movimientos, ganancias, conteos de efectivo, precios de compra/venta ni totales de plataforma. No crea automáticamente custodias de cierre del nuevo día. Es necesario confirmar al final qué documentos siguen físicamente en caja.

Si se envían saldos iniciales junto a una apertura trasladada, deben coincidir con el cierre. Es preferible omitirlos para evitar competir con el servidor. Cambiar la referencia trasladada requiere administrador. Un cambio de agencia o reinicio manual posterior se tramita con administrador, `apertura_manual: true`, saldos y motivo.

### 5.4 Actualización durante la jornada

`PATCH /api/arqueos/123`:

```json
{
  "biller_cordobas": "50.00",
  "biller_dolares": "0.00",
  "envios_cordobas": "0.00",
  "envios_dolares": "0.00",
  "pagos_cordobas": "0.00",
  "pagos_dolares": "0.00",
  "observacion": "Totales de plataforma revisados"
}
```

| Grupo | Regla |
| --- | --- |
| `biller_cordobas`, `biller_dolares` | Total diario declarado; acepta signo negativo y conserva el signo de origen. No es un saldo de plataforma. |
| `envios_cordobas`, `envios_dolares` | Totales de envíos de remesas de plataforma, no envíos DHL. No negativos. |
| `pagos_cordobas`, `pagos_dolares` | Totales de pagos de plataforma, se restan del esperado. No negativos. |
| `tasa_referencia` | Cambiarla en un arqueo existente requiere administrador. |
| Saldos iniciales y componentes de cheques | Solo se corrigen sin predecesor y con `motivo_apertura_manual`; un saldo trasladado es inmutable. |
| `observacion`, `motivo` | Texto opcional; `motivo` registra la razón en auditoría, no es columna de arqueo. |

Después de crear no se permite cambiar `fecha`, `id_usuario`, `id_subagencia`, `id_caja`, `id_arqueo_anterior` ni `apertura_manual`. No enviar `estado` para cerrar o anular: usar las acciones correspondientes.

### 5.5 Campos de respuesta calculados o administrativos

| Campos | Tipo / significado |
| --- | --- |
| `id`, `id_usuario`, `id_subagencia`, `id_caja`, `id_arqueo_anterior` | IDs; las dos últimas relaciones pueden ser null. |
| `fecha`, `estado` | Fecha operativa; `abierto`, `cerrado` o `anulado`. |
| `abierto_at`, `cerrado_at`, `cheques_confirmados_at`, `id_usuario_cierre` | Metadatos fijados por el servidor; los de cierre pueden ser null. |
| `caja_final_cordobas`, `caja_final_dolares` | Contado de efectivo más principal de cheques al cierre; 2 decimales. |
| `saldo_esperado_cordobas`, `saldo_esperado_dolares` | Esperado guardado en el cierre; 8 decimales. |
| `diferencia_cordobas`, `diferencia_dolares` | Contado menos esperado; 8 decimales, puede ser negativo. |
| `cantidad_cheques` | Número total de documentos en custodia de cierre, ambas monedas. |
| `monto_total_cheques`, `monto_total_cheques_dolares` | Principal de cierre: el nombre sin sufijo corresponde a **NIO**. |
| `ganancia_total_cheques`, `ganancia_total_cheques_dolares` | Ganancias de operaciones originadas en el día, separadas por moneda de ganancia. Sin sufijo = **NIO**. |
| `created_at`, `updated_at` | Timestamps de persistencia. |

Los resultados guardados pueden ser null mientras está abierto. Para una vista actual utilizar `/resumen`. El cliente no escribe resultados, totales derivados, autor, fechas de cierre ni timestamps.

## 6. Captura de movimientos financieros

En los recursos de esta sección `id_arqueo` es obligatorio al crear e inmutable. Los importes de movimiento son positivos; la dirección se expresa mediante `tipo`, no mediante un monto negativo. `estado` por defecto es `registrado`; también admite `anulado`. `observacion` admite texto/null hasta 10000 caracteres y `motivo` puede explicar una modificación en auditoría.

`fecha_operacion` es opcional y admite fecha/hora; si se informa, su fecha debe corresponder a la jornada. Omitirla no asigna automáticamente una hora real de operación: los timestamps de creación son otro dato.

### 6.1 Entradas y salidas

`POST /api/entradasysalidas`:

```json
{
  "id_arqueo": 123,
  "categoria": "gasto",
  "tipo": "Salida",
  "moneda": "NIO",
  "monto": "10.00",
  "medio": "efectivo",
  "detalle": "Compra de materiales",
  "fecha_operacion": "2026-11-04 10:15:00"
}
```

| Campo | Requisito |
| --- | --- |
| `categoria` | Obligatorio: `bancario`, `entre_agencias`, `gasto`, `varios`. |
| `tipo` | Obligatorio: `Entrada` o `Salida`. |
| `moneda` | Obligatorio: `NIO` o `USD`. |
| `monto` | Obligatorio, mayor que cero, hasta 2 decimales. |
| `medio` | Opcional/null: `efectivo`, `cheque`, `transferencia`, `mixto`. |
| `monto_efectivo`, `monto_cheques` | Obligatorios cuando `medio` es `mixto`; no negativos, suma igual a `monto`. En los demás medios se omiten o dejan null. |
| `detalle` | Texto/null hasta 255; obligatorio y no vacío para `gasto`. |
| `banco`, `numero_cuenta`, `referencia` | Opcionales/null; hasta 100 caracteres cada uno. |
| `id_subagencia_contraparte` | Opcional/null; solo para `entre_agencias`, debe ser otra agencia existente. |
| `id_movimiento_relacionado` | Opcional/null; vínculo supervisado con la declaración opuesta de otra jornada/agencia. |

Una remesa bancaria identificada por número tiene su recurso específico. No capturar la misma entrega bancaria dos veces en `entradasysalidas` y `remesasbancarias`.

### 6.2 Pases entre cajas

`POST /api/pases`:

```json
{
  "id_arqueo": 123,
  "tipo": "Salida",
  "moneda": "USD",
  "monto": "50.00",
  "medio": "efectivo",
  "id_caja_contraparte": 2,
  "detalle": "Caja 2",
  "referencia": "PASE-20261104-01"
}
```

Obligatorios: `id_arqueo`, `tipo`, `moneda`, `monto`. Opcionales/null: `medio`, `id_caja_contraparte`, `id_pase_relacionado`, `detalle` (255), `referencia` (100), `fecha_operacion`, `observacion`. `id_usuario` es opcional, pero si se envía debe coincidir con el propietario del arqueo; el servidor lo obtiene de la jornada.

Una caja contraparte debe ser distinta y de la misma agencia. El nombre textual se conserva aunque no se conozca el ID de caja histórica. Este recurso admite el medio `mixto` como clasificación, pero no tiene campos de desglose por componente.

**La API no crea automáticamente la entrada de la otra caja.** Cada cajero declara su movimiento. Relacionar o desvincular los IDs de las dos declaraciones requiere administrador. Deben pertenecer a jornadas distintas, tener moneda y monto iguales, direcciones opuestas y contrapartes compatibles. El enlace declarado no escribe automáticamente un enlace recíproco. No se permite anular mientras siga relacionado o referenciado por la contraparte; primero se desvincula con supervisión.

### 6.3 Remesas bancarias

`POST /api/remesasbancarias`:

```json
{
  "id_arqueo": 123,
  "banco": "Lafise",
  "tipo": "Salida",
  "moneda": "USD",
  "numero_remesa": "00001234",
  "monto": "100.00",
  "medio": "efectivo"
}
```

Obligatorios: `id_arqueo`, `banco`, `tipo`, `moneda`, `numero_remesa` y `monto`. `banco` y `numero_remesa` son texto de hasta 100 caracteres. Opcionales/null: `medio`, `fecha_operacion`, `observacion`; admite `estado`. No confundir una salida de efectivo hacia el banco con una transferencia entre cuentas: en el primer caso el efectivo sale de la caja y debe afectar el esperado.

### 6.4 Efecto del medio de movimiento

El resumen actual suma o resta el importe completo de movimientos `efectivo`, `cheque`, `mixto` y de aquellos con medio null. Un medio `transferencia` queda fuera del saldo físico combinado de efectivo y cheques. El desglose mixto preserva información, pero no provoca un segundo movimiento ni crea custodias de cheques.

Indicar el medio real cuando se conoce. No usar `transferencia` para ocultar una entrega física, ni generar movimientos adicionales a partir del desglose de uno ya registrado.

## 7. Cheques: operación e inventario documental

### 7.1 Registro de la operación original

`POST /api/cheques`:

```json
{
  "id_arqueo": 123,
  "moneda": "NIO",
  "numero_cheque": "000456",
  "banco": "Banco de ejemplo",
  "monto": "500.00",
  "ganancia": "10.00",
  "moneda_ganancia": "NIO",
  "efectivo_entregado": "490.00",
  "estado": "en_custodia"
}
```

| Campo | Requisito |
| --- | --- |
| `id_arqueo`, `moneda` | Obligatorios; el arqueo es donde se registra por primera vez la operación. |
| `monto` | Principal positivo de 2 decimales, opcional/null si el Excel solo informa ganancia. |
| `ganancia` | Opcional, no negativa, 2 decimales; por defecto cero. |
| `moneda_ganancia` | Opcional/null; si falta toma la moneda del cheque. Puede diferir de ella. |
| `efectivo_entregado` | Opcional/null, no negativo, 2 decimales. Si la moneda de ganancia coincide, no puede superar el principal conocido. |
| `numero_cheque`, `banco` | Opcionales/null; texto hasta 100. |
| `id_cliente` | Opcional/null; cliente existente, no crea uno. |
| `fecha_operacion`, `fecha_liquidacion` | Opcionales/null; liquidación no anterior a operación si ambas existen. |
| `estado` | `registrado`, `en_custodia`, `depositado`, `transferido`, `devuelto`, `anulado`. |
| `observacion` | Opcional/null, hasta 10000. |

El resumen añade **solo la ganancia** de los cheques originados en el día y no anulados, en su moneda de ganancia. `monto` y `efectivo_entregado` no se suman/restan automáticamente como movimientos independientes: se controla el activo combinado efectivo + cheques. El efectivo físico y la custodia de cierre determinan el contado.

Ejemplo aislado: caja inicial 1000; se recibe cheque de 500, se entregan 490 y se cobra ganancia 10. El esperado combinado pasa a 1010. Si quedan 510 de efectivo y el cheque de 500 en custodia de cierre, el contado es 1010. Registrar además una salida independiente de 490 para el mismo cambio duplicaría su efecto en este esquema.

Si un cheque ya estaba contenido en la apertura manual, documentarlo con ganancia del día cero y una observación de origen, sin volver a cobrar una ganancia histórica.

### 7.2 Custodia de apertura y cierre

`POST /api/custodiascheques`:

```json
{"id_arqueo": 123, "id_cheque": 40, "momento": "cierre"}
```

`id_arqueo`, `id_cheque` y `momento` son obligatorios. `momento` es `apertura` o `cierre`. Opcionales: `observacion`, `moneda`, `monto`; los dos últimos se obtienen del cheque y, si se envían, deben coincidir. El cheque debe estar vigente y tener principal conocido. La combinación arqueo/cheque/momento es única.

La custodia es la lista explícita de documentos presentes; no se deriva de `estado: en_custodia`. Consultar `/custodiascheques?id_arqueo=123&momento=cierre` o `/arqueos/123/registros` para obtener los documentos del cierre. `/cheques/arqueo/123` devuelve las operaciones originadas ese día y puede excluir cheques arrastrados de días anteriores.

En una apertura manual, capturar las custodias de apertura que expliquen el principal inicial declarado. Antes de cerrar, el servidor exige que su suma coincida si el componente inicial de cheques es conocido. Si es null, la API conserva esa incertidumbre y no inventa una composición cero.

En continuidad, las custodias de apertura se copian automáticamente y son inmutables. Al cierre se registran de nuevo los documentos que continúan presentes. Un cajero puede contar un cheque propio de la jornada o uno recibido en la apertura; los demás casos requieren supervisión. No crear otra operación de cheque para arrastrarlo: repetiría la ganancia del origen.

El principal y la moneda de un cheque no pueden cambiar mientras tenga custodias; tampoco puede anularse en ese caso. Los inventarios de jornadas cerradas quedan protegidos.

### 7.3 Seguimiento después del cierre

Un cheque cuyo origen ya está cerrado permite PATCH únicamente con `estado`, `fecha_liquidacion`, `observacion` y `motivo`. No enviar `id_arqueo` ni otros campos, aunque conserven sus valores. No puede anularse ni reactivarse mediante seguimiento. Actualizar el estado documental no retira automáticamente el cheque de las custodias ni registra una salida bancaria; esas declaraciones se realizan explícitamente en la jornada correspondiente.

## 8. Tasas y operaciones de cambio de caja

### 8.1 Precios del día

`POST /api/tasascambioarqueo`:

```json
{"id_arqueo": 123, "tipo": "Compra", "numero_precio": 1, "tasa": "36.500000"}
```

Los cuatro campos son obligatorios. `tipo` es `Compra`/`Venta`; `numero_precio` es un entero de 1 a 4; `tasa` es positiva y admite 6 decimales. Solo puede existir una combinación jornada/tipo/número. En PATCH solo cambia la tasa; jornada, tipo y número son inmutables. No acepta `observacion` como campo propio. DELETE elimina el precio si las operaciones referenciadas lo permiten.

### 8.2 Operación individual

`POST /api/operacionescambiocaja`:

```json
{
  "id_arqueo": 123,
  "tipo": "Compra",
  "monto_dolares": "5.83",
  "tasa": "36.500000",
  "id_tasa_arqueo": 31,
  "medio_entrada": "efectivo",
  "medio_salida": "efectivo"
}
```

| Campo | Requisito |
| --- | --- |
| `id_arqueo`, `tipo`, `monto_dolares`, `tasa` | Obligatorios. USD positivo, hasta 2 decimales; tasa positiva, hasta 6. |
| `monto_cordobas` | Opcional; si se envía debe ser positivo y admite hasta 8 decimales. No admite null explícito. |
| `id_tasa_arqueo` | Opcional/null; debe pertenecer a esta jornada y tipo. En una nueva operación su precio debe coincidir con `tasa`. |
| `id_mesa_cambio` | Opcional/null; enlace único a una mesa existente compatible. |
| `id_cliente` | Opcional/null; cliente existente. |
| `medio_entrada`, `medio_salida` | Opcionales/null; `efectivo`, `cheque`, `transferencia`, `mixto`. |
| `fecha_operacion`, `observacion`, `estado` | Según reglas comunes de captura. |

Omitir `monto_cordobas` calcula el producto exacto: el ejemplo devuelve `"212.79500000"`. Se puede capturar un importe aplicado distinto del producto para conservar el registro de origen. No hay una política de redondeo de pagos impuesta por este módulo. El escritorio no debe redondear a dos decimales un valor histórico al volver a guardarlo.

En PATCH, si cambia `tasa` o `monto_dolares` y se omite `monto_cordobas`, se recalcula este último. Modificar posteriormente el precio configurado no recalcula operaciones existentes; cada operación conserva su tasa aplicada.

Desde la perspectiva de la caja:

| Tipo | Entrada | Salida |
| --- | --- | --- |
| `Compra` | USD | NIO |
| `Venta` | NIO | USD |

Cada lado se excluye del esperado físico cuando su medio es `transferencia`. El medio `mixto` de esta operación es una clasificación sin desglose de componentes en este recurso.

### 8.3 Convivencia con las mesas existentes

Los registros históricos de `mesas_cambio` no se incorporan automáticamente al resumen de caja. Para incorporar una mesa existente se crea una operación de caja con `id_mesa_cambio`; el servidor valida propietario, fecha, tipo, estado, importes, tasa y cliente. El enlace es único y no altera la mesa. No generar dos operaciones de caja para el mismo hecho.

El alta histórica de una mesa y el alta de su operación de caja son peticiones diferentes; no existe una transacción HTTP conjunta ni compensación automática entre ambos módulos. El escritorio debe mostrar y reconciliar una creación parcial si falla la segunda petición. Un borrado/anulación en un módulo no sincroniza automáticamente el otro.

## 9. Conteo físico por denominaciones

`POST /api/detallearqueo`, una vez por moneda:

```json
{
  "id_arqueo": 123,
  "moneda": "NIO",
  "estado": "contado",
  "mil": 1,
  "veinte": 2
}
```

```json
{"id_arqueo": 123, "moneda": "USD", "estado": "contado", "cien": 1}
```

| Campo de cantidad | Valor por pieza | NIO | USD |
| --- | --- | --- | --- |
| `mil` | 1000 | Sí | Solo cero |
| `quinientos` | 500 | Sí | Solo cero |
| `doscientos` | 200 | Sí | Solo cero |
| `cien` | 100 | Sí | Sí |
| `cincuenta` | 50 | Sí | Sí |
| `veinte` | 20 | Sí | Sí |
| `diez` | 10 | Sí | Sí |
| `cinco` | 5 | Sí | Sí |
| `dos` | 2 | Solo cero | Sí |
| `uno` | 1 | Sí | Sí |

`id_arqueo` y `moneda` son obligatorios al crear e inmutables. Las cantidades son enteras no negativas hasta 4294967295. Los campos de cantidad omitidos al crear se guardan en cero. En PATCH, los omitidos conservan su valor: para limpiar una denominación es necesario enviarla con cero.

`estado` es `borrador` o `contado`; por defecto `borrador`. `observacion` es opcional/null. El servidor calcula `total` con dos decimales y asigna `contado_at` cuando está contado. En borrador ambos quedan null. **No enviar `total` ni `contado_at`**, pues están prohibidos.

La apertura no crea automáticamente estos conteos. Para corregir un conteo existente utilizar PATCH con su ID; un segundo POST de la misma moneda falla. Un conteo de cero piezas sigue necesitando su fila con `estado: contado`. Los cheques se cuentan en custodias y no se incluyen como piezas.

El formato actual reproduce las denominaciones enteras del Excel revisado. No expone campos para centavos o monedas fraccionarias. No inventar columnas como `centavos` o alterar `total` para compensarlos; una ampliación requerirá un cambio de contrato.

## 10. Resumen y cierre

### 10.1 Resumen actualizado

`GET /api/arqueos/123/resumen` devuelve:

```json
{
  "Arqueo": {"id": 123, "fecha": "2026-11-04", "estado": "abierto"},
  "Resumen": {
    "pendientes": [],
    "esperado": {"NIO": "1040.00000000", "USD": "100.00000000"},
    "contado": {"NIO": "1040.00000000", "USD": "100.00000000"},
    "diferencia": {"NIO": "0.00000000", "USD": "0.00000000"},
    "cheques": {"NIO": "0.00000000", "USD": "0.00000000"},
    "cantidad_cheques": 0,
    "ganancias_cheques": {"NIO": "0.00000000", "USD": "0.00000000"}
  },
  "Status": 200
}
```

El objeto `Arqueo` del ejemplo está abreviado; en la respuesta se devuelve el modelo con sus columnas. Estos números corresponden al ejemplo continuo: apertura 1000 NIO/100 USD, biller 50 NIO, gasto 10 NIO, demás totales cero, sin otros movimientos y conteos de la sección 9. Los ejemplos aislados de pases, cheques y remesas no deben añadirse a este escenario si se esperan estos resultados.

La fórmula por moneda es:

```text
esperado = saldo inicial que incluye cheques
         + biller con su signo + envíos de plataforma - pagos de plataforma
         + entradas - salidas de movimientos, pases y remesas bancarias
         + entradas - salidas de operaciones de cambio
         + ganancias de cheques originados ese día

contado = efectivo por denominaciones + principal en custodias de cierre
diferencia = contado - esperado
```

Se excluyen operaciones anuladas y los lados expresamente identificados como transferencia. Las ganancias se agregan en su moneda de ganancia. DHL, enrolamientos, papelería y conteos de servicios no generan ingresos automáticos. NIO y USD se mantienen separados; una diferencia positiva significa sobrante, una negativa faltante. No compensar una moneda con la otra mediante `tasa_referencia`.

`pendientes` contiene nombres de totales no informados y/o `conteo_NIO`, `conteo_USD`. Si falta cualquiera de los seis totales, ambos esperados y ambas diferencias son null. Si están los seis pero falta un conteo, el esperado está disponible y el contado/diferencia de esa moneda queda null. null se presenta como pendiente, no como cero.

**Alcance de la vista previa:** `pendientes: []` no garantiza por sí solo que el cierre será aceptado. La coincidencia documental de cheques de apertura se valida estrictamente en el cierre; la confirmación de cheques también se exige entonces. El cliente debe manejar un eventual 422 después de consultar el resumen.

### 10.2 Cierre definitivo

`POST /api/arqueos/123/cerrar`:

```json
{"confirmar_cheques": true, "motivo": "Conteo físico y documentos de caja verificados"}
```

`confirmar_cheques` es obligatorio y debe aceptarse explícitamente, incluso si el inventario es cero; `motivo` es opcional/null hasta 10000 caracteres. La interfaz debe presentar la lista final de cheques y la confirmación al cajero.

El servidor exige jornada abierta, seis totales informados, ambos conteos contados y coherencia del inventario inicial conocido. Recalcula y guarda finales, esperados, diferencias, principal y ganancias de cheques, autor y timestamps del cierre. La transacción incluye auditoría.

La respuesta es HTTP 200 con `Arqueo` cerrado y `Status: 200`, **sin clave `Resumen`**. No enviar finales ni diferencias en el cierre esperando sobrescribir el cálculo. Una diferencia por sí misma no impide cerrar: el escritorio debe mostrarla antes de confirmar. No hay endpoint de reapertura.

Los registros financieros quedan bloqueados después del cierre, también para administrador. Solo se permite el seguimiento específico de DHL y cheques descrito en esta guía. Anular un arqueo usa DELETE con motivo y solo admite jornadas abiertas; el estado anulado también bloquea nuevas capturas.

### 10.3 Secuencia de pantallas sugerida

1. Iniciar sesión y consultar `/arqueos?estado=abierto`. Recuperar el arqueo existente si lo hay, sin crear otro al abrir una ventana.
2. Si no hay jornada abierta, consultar `/arqueos/ultimo/{id_usuario}` y abrir según primera apertura o continuidad. Un último anulado no debe elegirse manualmente como predecesor.
3. Cargar catálogos y `/arqueos/{id}/registros`. Conservar los IDs devueltos para ediciones.
4. Capturar registros y seis totales. Tras cada escritura confirmada, actualizar la vista o volver a pedir resumen.
5. Cargar/guardar conteos de ambas monedas y custodias de cierre. Revisar pendientes, diferencias y documentos.
6. Solicitar confirmación de cierre al cajero; enviar la acción de cierre una sola vez y volver a leer la jornada.
7. Habilitar consultas del histórico y seguimientos permitidos. La nueva fecha usa una nueva apertura, sin reutilizar el ID anterior.

No ejecutar simultáneamente guardados pendientes y cierre desde botones diferentes. Las escrituras individuales están protegidas por transacciones; no existe una transacción que abarque toda una pantalla o varias peticiones HTTP.

## 11. DHL y sus detalles

`POST /api/enviosdhl` representa un envío realizado, tanto para el formato detallado como para el listado abreviado del Excel de enrolamientos:

```json
{
  "id_arqueo": 123,
  "guia": "0012345678",
  "fecha_envio": "2026-11-04",
  "nombre_contacto": "Persona de ejemplo",
  "telefono_contacto": "00000000",
  "tipo": "DOC",
  "destino": "Destino de ejemplo",
  "estado_envio": "ENVIADO"
}
```

| Campo | Regla |
| --- | --- |
| `id_arqueo`, `guia` | Obligatorios; guía texto hasta 100. |
| `id_cliente` | Opcional/null; cliente existente. |
| `fecha_envio` | Opcional/null, `yyyy-MM-dd`; no se deduce automáticamente del arqueo. |
| `fecha_original` | Opcional/null, texto hasta 100; conserva una fecha no normalizable. |
| `nombre_remitente`, `nombre_beneficiario`, `nombre_contacto` | Opcionales/null, hasta 255 cada uno. No inventar personas faltantes. |
| `telefono_contacto` | Opcional/null, texto hasta 50. |
| `tipo` | Opcional/null, texto hasta 30; conserva `DOC`, `PAQ` u otro valor original, sin normalización de tipo financiero. |
| `descripcion` | Opcional/null, hasta 10000. |
| `destino` | Opcional/null, hasta 255. |
| `estado_envio`, `confirmacion_original` | Opcionales/null, texto hasta 100. Son datos de seguimiento. |
| `fecha_entrega` | Opcional/null, fecha/hora válida; no anterior a envío si ambas fechas están informadas. |
| `fecha_entrega_original` | Opcional/null, texto hasta 150. |
| `estado` | Estado de captura: `borrador`, `registrado`, `anulado`. Distinto de `estado_envio`. |
| `observacion` | Opcional/null, hasta 10000. |

Si no se indica estado al crear, con fecha de envío válida toma `registrado` y sin ella `borrador`. Se permiten guías repetidas para revisión posterior: que un POST sea aceptado no demuestra que el envío no estuviera ya capturado. No hay verificación externa de DHL ni cobro monetario automático.

`POST /api/detallesenviosdhl` añade un desglose opcional:

```json
{
  "id_envio_dhl": 80,
  "descripcion": "Documentos",
  "cantidad": 1,
  "unidad": "sobre",
  "peso": "0.250"
}
```

Obligatorios: `id_envio_dhl` y `descripcion` (hasta 10000). Opcionales/null: `cantidad` (entero de 1 a 4294967295), `unidad` (50), `peso` (positivo, hasta 3 decimales), `observacion` (10000). El ID del envío es inmutable; la jornada se obtiene del envío, no se envía `id_arqueo` como sustituto. No tiene campo de estado.

Después del cierre solo se admite PATCH del envío con `estado_envio`, `confirmacion_original`, `fecha_entrega`, `fecha_entrega_original`, `observacion` y `motivo`. El cuerpo debe contener exclusivamente esas claves. No se permite cambiar guía, personas, fecha de envío, detalles ni relaciones, ni anular la captura cerrada.

## 12. Enrolamientos, conteos de servicios y papelería

### 12.1 Enrolamientos por persona

`POST /api/enrolamientos`:

```json
{
  "id_arqueo": 123,
  "nombre_completo": "Persona de ejemplo",
  "tipo_documento": "Cédula",
  "numero_documento": "001-000000-0000A",
  "fecha_enrolamiento": "2026-11-04",
  "usuario_servicio": "14514",
  "estado": "registrado"
}
```

| Campo | Regla |
| --- | --- |
| `id_arqueo`, `nombre_completo`, `numero_documento` | Obligatorios. Nombre hasta 255; documento texto hasta 100. |
| `tipo_documento` | Opcional/null, texto hasta 50. |
| `id_cliente`, `id_servicio` | Opcionales/null; referencias existentes. |
| `fecha_enrolamiento` | Opcional/null, `yyyy-MM-dd`; obligatoria para `estado: registrado`. No se toma automáticamente de la jornada. |
| `fecha_original` | Opcional/null, texto hasta 100 para preservar el valor de origen. |
| `usuario_servicio` | Opcional/null, código externo como texto hasta 100. **No es `users.id`**. |
| `estado` | `borrador`, `registrado`, `anulado`; por defecto borrador. |
| `observacion` | Opcional/null, hasta 10000. |

Vincular un cliente verifica su documento, ignorando espacios, guiones y diferencias de mayúsculas. No crea ni actualiza clientes. Se permiten varios eventos de la misma persona; el documento no es una clave única de enrolamiento.

Los listados personales y los totales por servicio del Excel son fuentes independientes; no asociar personas a servicios por coincidencia de número de fila. Una fecha de origen inválida se conserva en `fecha_original` y se deja la normalizada pendiente, en lugar de inventar la fecha actual.

### 12.2 Conteos diarios de servicios

`POST /api/conteosservicios`:

```json
{"id_arqueo": 123, "id_servicio": 9, "cantidad": 0, "estado": "registrado"}
```

Los primeros tres campos son obligatorios; `cantidad` es un entero entre 0 y 4294967295. Admite `observacion` y estado `registrado`/`anulado`. La combinación jornada/servicio es única, incluso después de anular; actualizar la fila existente en lugar de crear otra. Si procede reactivarla mientras está abierto, PATCH `estado: registrado` con el contexto de corrección.

Estos conteos no se calculan contando enrolamientos ni operaciones financieras y no producen importes en caja. El servicio se obtiene del catálogo y no de una lista de IDs fijos en el escritorio.

### 12.3 Papelería

`POST /api/conteospapeleria`:

```json
{"id_arqueo": 123, "concepto": "Sobres", "cantidad": 12}
```

Obligatorios: `concepto` (texto hasta 100), `cantidad` (entero 0 a 4294967295). Opcionales/null: `id_arqueo`, `id_subagencia`, `id_servicio`, `fecha` y `observacion` (10000). `id_usuario` es opcional, por defecto el autenticado; otro usuario requiere administrador.

Con arqueo, el servidor toma propietario, agencia y fecha de la jornada. Una fecha/agencia proporcionada debe coincidir. Sin arqueo puede conservarse fecha null para un dato sin periodo conocido; no se transforma automáticamente en un movimiento del día. Propietario y arqueo son inmutables tras la creación. No se interpreta automáticamente como inventario inicial, consumo o saldo, ni se valorizan las cantidades.

## 13. Catálogos administrables

Las lecturas siguen las reglas de alcance de la sección 2. Las escrituras de estos tres recursos requieren administrador.

| Recurso | Campos POST obligatorios | Opcionales y reglas |
| --- | --- | --- |
| `cajas` | `id_subagencia`, `codigo` (30), `nombre` (100) | `estado`: activo/inactivo, por defecto activo. Código único por agencia. |
| `servicioscaja` | `codigo` (50), `nombre` (100) | `categoria` texto/null (100), `estado`: activo/inactivo. Código único global, inmutable cuando ya fue utilizado. |
| `precioproveedordolar` | `proveedor_actual` (255), `precio_dolar` positivo de hasta 6 decimales | `id_subagencia` null = global; `vigente_desde` fecha/hora, por defecto ahora; `estado` activo/inactivo; `id_usuario` es autor autenticado e inmutable. |

Todos admiten PATCH parcial y DELETE con motivo, que los inactiva conservando referencias. No hay seeds de servicios ni correspondencias automáticas entre nombres de Excel y códigos; configurar el catálogo real antes de usar IDs en el escritorio.

La tasa de referencia del arqueo, los precios diarios compra/venta y la cotización del proveedor son datos separados. Consultar el último proveedor puede ayudar a mostrar una referencia actual, pero no reemplaza la tasa histórica de una operación ni modifica jornadas.

## 14. Extracción para verificación e historial

### 14.1 Todos los registros de una jornada

`GET /api/arqueos/123/registros` devuelve una envoltura `Arqueo`, no un archivo Excel ni un array independiente por endpoint. Dentro del arqueo se cargan:

| Propiedad JSON anidada | Contenido |
| --- | --- |
| `caja` | Caja o null. |
| `subagencia` | Agencia de la jornada. |
| `detalle_arqueo` | Array de conteos de efectivo. |
| `cheques` | Array de operaciones de cheque originadas en el día. |
| `custodias` | Array de inventarios apertura/cierre; cada elemento contiene su objeto `cheque`. |
| `tasas` | Precios del día. |
| `operaciones_cambio` | Operaciones individuales de compra/venta. |
| `pases` | Pases declarados. |
| `entradas_y_salidas` | Movimientos por categoría. |
| `remesas` | Remesas bancarias. |
| `envios_dhl` | Envíos; cada uno contiene un array `detalles`. |
| `enrolamientos` | Eventos de enrolamiento. |
| `conteos_servicios` | Conteos; cada uno contiene su objeto `servicio`. |
| `papeleria` | Conteos vinculados a esta jornada. |

Las relaciones se entregan sin paginación en esta consulta; las listas generales sí se paginan. No incluye el objeto `Resumen`, el historial ni papelería que no esté vinculada a la jornada. Las listas anidadas no tienen un orden de negocio explícito garantizado: ordenar por ID u otro criterio en el consumidor si es necesario.

La extracción incluye estados borrador y anulado para que el proceso verificador distinga datos originales. Filtrar sus estados según el informe; no sumarlos indiscriminadamente. Las tablas protegidas se consultan a través de relaciones, sin transformarlas ni escribirlas.

### 14.2 Auditoría

```text
GET /api/historialcaja?id_arqueo=123&per_page=100&page=1
GET /api/historialcaja?entidad=operaciones_cambio_caja&id_registro=55
GET /api/historialcaja/200
```

La lista y la consulta individual comparten el nombre `Historial`; la primera contiene un array y `Paginacion`, la segunda un objeto. Campos del registro: `id`, `id_usuario` (actor), `id_arqueo` nullable, `entidad` (nombre real de tabla), `id_registro`, `accion`, `antes`, `despues`, `motivo`, `ip`, `created_at`, `updated_at`.

Acciones actuales: `abrir`, `arrastrar`, `crear`, `actualizar`, `anular`, `eliminar`, `cerrar`. `antes` puede ser null al crear; `despues` es null al eliminar físicamente. Los snapshots conservan atributos de persistencia y no garantizan los mismos casts de tipos que la respuesta del modelo: tratarlos como JSON dinámico. No hay rutas para escribir o eliminar historial.

## 15. Errores, conflictos y repetición de peticiones

| HTTP | Tratamiento del escritorio |
| --- | --- |
| 200 | Operación o consulta correcta. Leer la envoltura correspondiente. |
| 201 | Registro creado. Conservar el ID del cuerpo. |
| 400 | Contrato histórico de validación de login; leer `Errores`. |
| 401 | Sesión no válida, ausente o revocada; solicitar autenticación. También contraseña incorrecta en login. |
| 403 | Falta de privilegio o usuario inactivo; no reintentar sin cambio de condiciones. |
| 404 | Registro inexistente/no visible, consulta «último» sin resultados, o usuario de login inexistente. |
| 409 | Restricción de unicidad o dependencia SQL que impide el cambio. Consultar el recurso y reconciliar. |
| 422 | Campos o reglas de negocio inválidos. Mostrar mensajes por campo. |
| 500 u otro 5xx | Fallo del servidor. Conservar información para soporte y comprobar el estado antes de repetir escrituras. |

Ejemplo representativo de Laravel para 422 con `Accept: application/json`:

```json
{
  "message": "Faltan datos: biller_cordobas, conteo_USD",
  "errors": {
    "cierre": ["Faltan datos: biller_cordobas, conteo_USD"]
  }
}
```

El texto superior exacto depende de Laravel, traducciones y número de errores. Los campos `message`, `errors`, `Mensaje` y `Errores` no son intercambiables por un único contrato histórico. Los errores no están obligados a incluir `Status`; la fuente principal del estado es el código HTTP. Un proxy también puede responder texto o HTML: conservar el cuerpo sin asumir JSON válido.

Reglas de negocio como segunda apertura, conteo duplicado o cierre incompleto suelen devolver 422 antes de llegar a una restricción SQL; otros conflictos pueden dar 409. La interfaz debe manejar ambos. Un cierre repetido devuelve error de jornada ya cerrada; consultar el arqueo para determinar si la primera petición terminó correctamente.

El módulo individual no expone `Idempotency-Key`, ETag ni control general de versión para sus escrituras. Los consolidados administrativos sí tienen versiones de corte. No hay endpoint de captura por lotes ni garantía general contra duplicados por repetir un POST: algunas combinaciones son únicas, otras (como guías DHL o enrolamientos) deliberadamente no. Tras un timeout o pérdida de red, el resultado de la escritura es desconocido; consultar los registros antes de reenviar. Evitar reintentos automáticos de escrituras y bloquear el doble clic mientras se procesa una operación.

## 16. Cliente C# de referencia

El siguiente bloque es autocontenido para un archivo `ApiCajaClient.cs`, con C# 10 y APIs disponibles desde .NET 6. Usa `System.Text.Json` y `HttpClient`, sin dependencias externas. Las fechas se mantienen como texto para respetar los formatos mixtos de este backend. Este ejemplo no determina la versión real del proyecto de escritorio.

En un proyecto .NET Framework antiguo no se debe copiar sin adaptar el código: conservar los contratos JSON y trasladar la serialización al mecanismo ya utilizado por el proyecto. Con Newtonsoft.Json, por ejemplo, los atributos de nombre y el manejo de decimales deben expresarse con sus equivalentes; `JsonPropertyName` pertenece a System.Text.Json.

Los DTO de lectura incluyen los campos principales y un diccionario de campos adicionales. Los demás recursos pueden consumirse inicialmente como `JsonElement` o tiparse según las tablas anteriores. No reutilizar un DTO de lectura como cuerpo de escritura.

```csharp
#nullable enable
using System;
using System.Collections.Generic;
using System.Globalization;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;

namespace ControlCaja.Integracion
{
    public sealed class ApiCajaException : Exception
    {
        public HttpStatusCode HttpStatus { get; }
        public Dictionary<string, string[]> Errores { get; }
        public string CuerpoOriginal { get; }

        public ApiCajaException(HttpStatusCode status, string mensaje,
            Dictionary<string, string[]> errores, string cuerpo) : base(mensaje)
        {
            HttpStatus = status;
            Errores = errores;
            CuerpoOriginal = cuerpo;
        }
    }

    public sealed class ApiCajaClient
    {
        private readonly HttpClient http;
        private readonly JsonSerializerOptions json = new JsonSerializerOptions
        {
            PropertyNameCaseInsensitive = true,
            NumberHandling = JsonNumberHandling.AllowReadingFromString
        };

        public ApiCajaClient(HttpClient httpClient, Uri direccionApi)
        {
            // Utilizar un HttpClient dedicado a esta API y reutilizarlo por sesión.
            http = httpClient;
            if (!direccionApi.IsAbsoluteUri)
                throw new ArgumentException("La URL base debe ser absoluta.", nameof(direccionApi));
            http.BaseAddress = new Uri(direccionApi.AbsoluteUri.TrimEnd('/') + "/");
            http.DefaultRequestHeaders.Accept.Clear();
            http.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        }

        public void EstablecerToken(string token) =>
            http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);

        public void LimpiarToken() => http.DefaultRequestHeaders.Authorization = null;

        public async Task<LoginRespuesta> LoginAsync(string usuario, string clave,
            CancellationToken ct = default)
        {
            LimpiarToken();
            var cuerpo = new Dictionary<string, object?>
            {
                ["usuario"] = usuario, ["contraseña"] = clave
            };
            var resultado = await EnviarAsync<LoginRespuesta>(HttpMethod.Post, "login", cuerpo, ct)
                .ConfigureAwait(false);
            if (string.IsNullOrWhiteSpace(resultado.AccessToken))
                throw new JsonException("La respuesta no incluye access_token.");
            EstablecerToken(resultado.AccessToken);
            return resultado;
        }

        public async Task LogoutAsync(CancellationToken ct = default)
        {
            await EnviarAsync<JsonElement>(HttpMethod.Post, "logout", null, ct).ConfigureAwait(false);
            LimpiarToken();
        }

        public Task<ListaArqueosRespuesta> ListarArqueosAsync(string consulta = "",
            CancellationToken ct = default) =>
            EnviarAsync<ListaArqueosRespuesta>(HttpMethod.Get, "arqueos" + consulta, null, ct);

        public Task<ArqueoRespuesta> ObtenerArqueoAsync(long id, CancellationToken ct = default) =>
            EnviarAsync<ArqueoRespuesta>(HttpMethod.Get, $"arqueos/{id}", null, ct);

        public Task<ArqueoRespuesta> AbrirAsync(object datos, CancellationToken ct = default) =>
            EnviarAsync<ArqueoRespuesta>(HttpMethod.Post, "arqueos", datos, ct);

        public Task<ArqueoRespuesta> ActualizarArqueoAsync(long id, object cambios,
            CancellationToken ct = default) =>
            EnviarAsync<ArqueoRespuesta>(HttpMethod.Patch, $"arqueos/{id}", cambios, ct);

        public Task<ResumenRespuesta> ResumenAsync(long id, CancellationToken ct = default) =>
            EnviarAsync<ResumenRespuesta>(HttpMethod.Get, $"arqueos/{id}/resumen", null, ct);

        public Task<DetalleArqueoRespuesta> CrearConteoAsync(object datos,
            CancellationToken ct = default) =>
            EnviarAsync<DetalleArqueoRespuesta>(HttpMethod.Post, "detallearqueo", datos, ct);

        public Task<DetalleArqueoRespuesta> ActualizarConteoAsync(long id, object cambios,
            CancellationToken ct = default) =>
            EnviarAsync<DetalleArqueoRespuesta>(HttpMethod.Patch, $"detallearqueo/{id}", cambios, ct);

        public Task<ArqueoRespuesta> CerrarAsync(long id, bool confirmarCheques,
            string? motivo = null, CancellationToken ct = default) =>
            EnviarAsync<ArqueoRespuesta>(HttpMethod.Post, $"arqueos/{id}/cerrar",
                new { confirmar_cheques = confirmarCheques, motivo }, ct);

        // Para tipar otros recursos, usar estas operaciones con su envoltura correspondiente.
        public Task<T> ConsultarAsync<T>(string ruta, CancellationToken ct = default) =>
            EnviarAsync<T>(HttpMethod.Get, ruta, null, ct);

        public Task<T> CrearAsync<T>(string recurso, object datos, CancellationToken ct = default) =>
            EnviarAsync<T>(HttpMethod.Post, recurso, datos, ct);

        public Task<T> ModificarAsync<T>(string recurso, long id, object cambios,
            CancellationToken ct = default) =>
            EnviarAsync<T>(HttpMethod.Patch, $"{recurso}/{id}", cambios, ct);

        public Task<JsonElement> RetirarAsync(string recurso, long id, string motivo,
            CancellationToken ct = default) =>
            EnviarAsync<JsonElement>(HttpMethod.Delete, $"{recurso}/{id}", new { motivo }, ct);

        // Valida precisión; no redondea silenciosamente datos de captura.
        public static string DecimalApi(decimal valor, int decimales)
        {
            if (decimales < 0 || decimales > 8) throw new ArgumentOutOfRangeException(nameof(decimales));
            if (decimal.Round(valor, decimales) != valor)
                throw new ArgumentException("El valor tiene más decimales que los admitidos.", nameof(valor));
            return valor.ToString("F" + decimales, CultureInfo.InvariantCulture);
        }

        private async Task<T> EnviarAsync<T>(HttpMethod metodo, string ruta, object? cuerpo,
            CancellationToken ct)
        {
            if (string.IsNullOrWhiteSpace(ruta) || ruta.StartsWith("/")
                || Uri.TryCreate(ruta, UriKind.Absolute, out _)
                || ruta.Contains("..") || ruta.Contains("\\"))
                throw new ArgumentException("Usar una ruta relativa de esta API, sin / inicial.", nameof(ruta));

            using var peticion = new HttpRequestMessage(metodo, ruta);
            if (cuerpo != null)
                peticion.Content = new StringContent(JsonSerializer.Serialize(cuerpo, json),
                    Encoding.UTF8, "application/json");

            using var respuesta = await http.SendAsync(peticion, ct).ConfigureAwait(false);
            string texto = await respuesta.Content.ReadAsStringAsync(ct).ConfigureAwait(false);
            if (!respuesta.IsSuccessStatusCode)
                throw LeerError(respuesta.StatusCode, texto);

            var resultado = JsonSerializer.Deserialize<T>(texto, json);
            if (resultado is null) throw new JsonException("La API devolvió una respuesta vacía.");
            return resultado;
        }

        private static ApiCajaException LeerError(HttpStatusCode status, string texto)
        {
            string mensaje = $"La API respondió HTTP {(int)status}.";
            var errores = new Dictionary<string, string[]>();
            try
            {
                using var documento = JsonDocument.Parse(texto);
                var raiz = documento.RootElement;
                if (raiz.ValueKind == JsonValueKind.Object)
                {
                    foreach (string clave in new[] { "message", "Mensaje", "error" })
                        if (raiz.TryGetProperty(clave, out var valor) && valor.ValueKind == JsonValueKind.String)
                        { mensaje = valor.GetString() ?? mensaje; break; }

                    foreach (string clave in new[] { "errors", "Errores" })
                    {
                        if (!raiz.TryGetProperty(clave, out var campos) || campos.ValueKind != JsonValueKind.Object)
                            continue;
                        foreach (var campo in campos.EnumerateObject())
                        {
                            var mensajes = new List<string>();
                            if (campo.Value.ValueKind == JsonValueKind.Array)
                                foreach (var item in campo.Value.EnumerateArray()) mensajes.Add(item.ToString());
                            else mensajes.Add(campo.Value.ToString());
                            errores[campo.Name] = mensajes.ToArray();
                        }
                    }
                }
            }
            catch (JsonException) { /* Se conserva el cuerpo original, incluso si no es JSON. */ }
            return new ApiCajaException(status, mensaje, errores, texto);
        }
    }

    public sealed class UsuarioSesionDto
    {
        [JsonPropertyName("id")] public long Id { get; set; }
        [JsonPropertyName("usuario")] public string Usuario { get; set; } = "";
        [JsonPropertyName("rol_usuario")] public string Rol { get; set; } = "";
        [JsonPropertyName("estado_usuario")] public string Estado { get; set; } = "";
        [JsonPropertyName("id_trabajador")] public long IdTrabajador { get; set; }
    }

    public sealed class LoginRespuesta
    {
        [JsonPropertyName("usuario")] public UsuarioSesionDto Usuario { get; set; } = new();
        [JsonPropertyName("access_token")] public string AccessToken { get; set; } = "";
        [JsonPropertyName("token_type")] public string TokenType { get; set; } = "";
        [JsonPropertyName("Status")] public int Status { get; set; }
    }

    public sealed class ArqueoDto
    {
        [JsonPropertyName("id")] public long Id { get; set; }
        [JsonPropertyName("id_usuario")] public long IdUsuario { get; set; }
        [JsonPropertyName("id_subagencia")] public long IdSubagencia { get; set; }
        [JsonPropertyName("id_caja")] public long? IdCaja { get; set; }
        [JsonPropertyName("id_arqueo_anterior")] public long? IdArqueoAnterior { get; set; }
        [JsonPropertyName("fecha")] public string Fecha { get; set; } = "";
        [JsonPropertyName("estado")] public string Estado { get; set; } = "";
        [JsonPropertyName("caja_inicial_cordobas")] public decimal CajaInicialCordobas { get; set; }
        [JsonPropertyName("caja_inicial_dolares")] public decimal CajaInicialDolares { get; set; }
        [JsonPropertyName("cheques_inicial_cordobas")] public decimal? ChequesInicialCordobas { get; set; }
        [JsonPropertyName("cheques_inicial_dolares")] public decimal? ChequesInicialDolares { get; set; }
        [JsonPropertyName("tasa_referencia")] public decimal? TasaReferencia { get; set; }
        [JsonPropertyName("biller_cordobas")] public decimal? BillerCordobas { get; set; }
        [JsonPropertyName("biller_dolares")] public decimal? BillerDolares { get; set; }
        [JsonPropertyName("envios_cordobas")] public decimal? EnviosCordobas { get; set; }
        [JsonPropertyName("envios_dolares")] public decimal? EnviosDolares { get; set; }
        [JsonPropertyName("pagos_cordobas")] public decimal? PagosCordobas { get; set; }
        [JsonPropertyName("pagos_dolares")] public decimal? PagosDolares { get; set; }
        [JsonPropertyName("caja_final_cordobas")] public decimal? CajaFinalCordobas { get; set; }
        [JsonPropertyName("caja_final_dolares")] public decimal? CajaFinalDolares { get; set; }
        [JsonPropertyName("saldo_esperado_cordobas")] public decimal? EsperadoCordobas { get; set; }
        [JsonPropertyName("saldo_esperado_dolares")] public decimal? EsperadoDolares { get; set; }
        [JsonPropertyName("diferencia_cordobas")] public decimal? DiferenciaCordobas { get; set; }
        [JsonPropertyName("diferencia_dolares")] public decimal? DiferenciaDolares { get; set; }
        [JsonPropertyName("cantidad_cheques")] public long? CantidadCheques { get; set; }
        [JsonPropertyName("monto_total_cheques")] public decimal? PrincipalChequesNio { get; set; }
        [JsonPropertyName("monto_total_cheques_dolares")] public decimal? PrincipalChequesUsd { get; set; }
        [JsonPropertyName("ganancia_total_cheques")] public decimal? GananciasChequesNio { get; set; }
        [JsonPropertyName("ganancia_total_cheques_dolares")] public decimal? GananciasChequesUsd { get; set; }
        [JsonPropertyName("observacion")] public string? Observacion { get; set; }
        [JsonPropertyName("abierto_at")] public string? AbiertoAt { get; set; }
        [JsonPropertyName("cerrado_at")] public string? CerradoAt { get; set; }
        [JsonPropertyName("cheques_confirmados_at")] public string? ChequesConfirmadosAt { get; set; }
        [JsonPropertyName("id_usuario_cierre")] public long? IdUsuarioCierre { get; set; }
        [JsonExtensionData] public Dictionary<string, JsonElement>? Otros { get; set; }
    }

    public sealed class ArqueoRespuesta
    {
        [JsonPropertyName("Arqueo")] public ArqueoDto Arqueo { get; set; } = new();
        [JsonPropertyName("Status")] public int Status { get; set; }
    }

    public sealed class PaginacionDto
    {
        [JsonPropertyName("total")] public long Total { get; set; }
        [JsonPropertyName("pagina")] public int Pagina { get; set; }
        [JsonPropertyName("por_pagina")] public int PorPagina { get; set; }
    }

    public sealed class ListaArqueosRespuesta
    {
        [JsonPropertyName("Arqueos")] public List<ArqueoDto> Arqueos { get; set; } = new();
        [JsonPropertyName("Paginacion")] public PaginacionDto Paginacion { get; set; } = new();
        [JsonPropertyName("Status")] public int Status { get; set; }
    }

    public sealed class ImportesMonedaDto
    {
        [JsonPropertyName("NIO")] public decimal? Nio { get; set; }
        [JsonPropertyName("USD")] public decimal? Usd { get; set; }
    }

    public sealed class ResumenDto
    {
        [JsonPropertyName("pendientes")] public List<string> Pendientes { get; set; } = new();
        [JsonPropertyName("esperado")] public ImportesMonedaDto Esperado { get; set; } = new();
        [JsonPropertyName("contado")] public ImportesMonedaDto Contado { get; set; } = new();
        [JsonPropertyName("diferencia")] public ImportesMonedaDto Diferencia { get; set; } = new();
        [JsonPropertyName("cheques")] public ImportesMonedaDto Cheques { get; set; } = new();
        [JsonPropertyName("cantidad_cheques")] public long CantidadCheques { get; set; }
        [JsonPropertyName("ganancias_cheques")] public ImportesMonedaDto GananciasCheques { get; set; } = new();
    }

    public sealed class ResumenRespuesta
    {
        [JsonPropertyName("Arqueo")] public ArqueoDto Arqueo { get; set; } = new();
        [JsonPropertyName("Resumen")] public ResumenDto Resumen { get; set; } = new();
        [JsonPropertyName("Status")] public int Status { get; set; }
    }

    public sealed class DetalleArqueoDto
    {
        [JsonPropertyName("id")] public long Id { get; set; }
        [JsonPropertyName("id_arqueo")] public long IdArqueo { get; set; }
        [JsonPropertyName("moneda")] public string Moneda { get; set; } = "";
        [JsonPropertyName("estado")] public string Estado { get; set; } = "";
        [JsonPropertyName("total")] public decimal? Total { get; set; }
        [JsonPropertyName("mil")] public uint Mil { get; set; }
        [JsonPropertyName("quinientos")] public uint Quinientos { get; set; }
        [JsonPropertyName("doscientos")] public uint Doscientos { get; set; }
        [JsonPropertyName("cien")] public uint Cien { get; set; }
        [JsonPropertyName("cincuenta")] public uint Cincuenta { get; set; }
        [JsonPropertyName("veinte")] public uint Veinte { get; set; }
        [JsonPropertyName("diez")] public uint Diez { get; set; }
        [JsonPropertyName("cinco")] public uint Cinco { get; set; }
        [JsonPropertyName("dos")] public uint Dos { get; set; }
        [JsonPropertyName("uno")] public uint Uno { get; set; }
        [JsonPropertyName("contado_at")] public string? ContadoAt { get; set; }
        [JsonExtensionData] public Dictionary<string, JsonElement>? Otros { get; set; }
    }

    public sealed class DetalleArqueoRespuesta
    {
        [JsonPropertyName("DetalleArqueo")] public DetalleArqueoDto Detalle { get; set; } = new();
        [JsonPropertyName("Status")] public int Status { get; set; }
    }
}
```

El `PropertyNameCaseInsensitive` no convierte snake_case a PascalCase: por eso se especifica `JsonPropertyName`. `AllowReadingFromString` permite leer importes entre comillas como `decimal`, manteniendo null en las propiedades nullable. No habilita por sí mismo la escritura de importes como cadenas; los cuerpos de ejemplo utilizan `DecimalApi` o cadenas explícitas.

Instanciar el cliente y su HttpClient dedicado una vez para su ámbito de uso; no reconstruir `ApiCajaClient` sobre un HttpClient que ya envió peticiones, porque la dirección base se configura en el constructor. El propietario del HttpClient gestiona su disposición cuando termina ese ámbito.

El cliente de referencia no aplica reintentos ni conecta el resultado a controles de interfaz. `HttpRequestException`, cancelaciones y timeout son distintos de `ApiCajaException` y deben manejarse en la capa de presentación. No cambiar token o URL de ese HttpClient desde peticiones paralelas de otra sesión. No registrar contraseñas ni tokens en logs.

## 17. Ejemplo C# de un ciclo completo

Este segundo bloque puede guardarse como `EjemploFlujoCaja.cs` junto al anterior. **Es una demostración que escribe registros** y se ejecuta exclusivamente con datos de prueba y un usuario sin jornadas previas. No debe conectarse a producción ni utilizarse como inicializador automático al abrir la aplicación. En la interfaz real se sustituyen los importes fijos por la captura del cajero y se implementa la recuperación de jornada y continuidad de la sección 10.3.

La confirmación se recibe mediante un callback de la interfaz, después de mostrar el resumen y verificar que el inventario de cheques es cero en este escenario. Si el usuario no confirma, la jornada permanece abierta. Los IDs se toman de las respuestas, no del número 123 de los ejemplos de contrato.

```csharp
#nullable enable
using System;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace ControlCaja.Integracion
{
    public static class EjemploFlujoCaja
    {
        public static async Task<ArqueoDto> EjecutarPrimeraJornadaAsync(
            HttpClient http, Uri direccionApi, string usuario, string clave,
            long idCaja, string fechaOperativa,
            Func<ResumenDto, Task<bool>> confirmarCierre,
            CancellationToken ct = default)
        {
            var api = new ApiCajaClient(http, direccionApi);
            var login = await api.LoginAsync(usuario, clave, ct);

            // Solo para esta demostración de primera apertura, no para el flujo cotidiano.
            var previas = await api.ListarArqueosAsync(
                $"?id_usuario={login.Usuario.Id}&per_page=1", ct);
            if (previas.Paginacion.Total != 0)
                throw new InvalidOperationException("La demostración requiere un usuario sin jornadas previas.");

            var apertura = await api.AbrirAsync(new
            {
                fecha = fechaOperativa,
                id_caja = idCaja,
                caja_inicial_cordobas = ApiCajaClient.DecimalApi(1000m, 2),
                caja_inicial_dolares = ApiCajaClient.DecimalApi(100m, 2),
                cheques_inicial_cordobas = "0.00",
                cheques_inicial_dolares = "0.00",
                tasa_referencia = ApiCajaClient.DecimalApi(36.7m, 6),
                motivo_apertura_manual = "Saldos iniciales de demostración verificados"
            }, ct);
            long idArqueo = apertura.Arqueo.Id;

            await api.ActualizarArqueoAsync(idArqueo, new
            {
                biller_cordobas = "50.00", biller_dolares = "0.00",
                envios_cordobas = "0.00", envios_dolares = "0.00",
                pagos_cordobas = "0.00", pagos_dolares = "0.00"
            }, ct);

            JsonElement gasto = await api.CrearAsync<JsonElement>("entradasysalidas", new
            {
                id_arqueo = idArqueo, categoria = "gasto", tipo = "Salida",
                moneda = "NIO", monto = "10.00", medio = "efectivo",
                detalle = "Compra de materiales de demostración"
            }, ct);
            long idGasto = gasto.GetProperty("Entrada salida").GetProperty("id").GetInt64();
            // Conservar idGasto en la vista para modificar/anular ese registro si se necesita.
            _ = idGasto;

            var nio = await api.CrearConteoAsync(new
            {
                id_arqueo = idArqueo, moneda = "NIO", estado = "contado", mil = 1, veinte = 2
            }, ct);
            var usd = await api.CrearConteoAsync(new
            {
                id_arqueo = idArqueo, moneda = "USD", estado = "contado", cien = 1
            }, ct);
            // Para corregir, usar nio.Detalle.Id / usd.Detalle.Id con ActualizarConteoAsync.
            _ = nio; _ = usd;

            var vista = await api.ResumenAsync(idArqueo, ct);
            if (vista.Resumen.Pendientes.Count != 0)
                throw new InvalidOperationException("Hay datos pendientes de captura.");

            // Mostrar contado, esperado, diferencias e inventario antes de esta confirmación.
            if (!await confirmarCierre(vista.Resumen)) return vista.Arqueo;

            var cierre = await api.CerrarAsync(idArqueo, true,
                "Conteos e inventario cero de cheques verificados en demostración", ct);
            return cierre.Arqueo;
        }
    }
}
```

Resultado esperado de la demostración: `estado: cerrado`, finales NIO `1040.00` y USD `100.00`, esperados `1040.00000000` / `100.00000000` y diferencias cero. No se crea un segundo arqueo automáticamente.

Para una apertura cotidiana posterior, el cuerpo se reduce a `{ fecha = fechaOperativa }` y se deja que el servidor traslade saldos. No volver a enviar los 1000/100 de la demostración. Para un PATCH intencional a null puede usarse, por ejemplo, un `Dictionary<string, object?>` con `["observacion"] = null`.

En WinForms/WPF, los eventos de interfaz llaman estos métodos con `await`. Evitar `.Result` y `.Wait()` en el hilo de interfaz. Deshabilitar el botón mientras está pendiente la petición y habilitarlo en `finally`. Mostrar `ApiCajaException.Errores` por campo; ante 401 recuperar sesión; ante 403/404 explicar falta de acceso o registro; ante fallo de red consultar el estado antes de repetir un alta o cierre.

## 18. Cambios que debe incorporar el escritorio existente

| Comportamiento anterior o supuesto | Adaptación necesaria |
| --- | --- |
| Crear arqueo solo con usuario y fecha | Primera apertura exige saldos totales y motivo; las siguientes siguen la continuidad validada. |
| Editar libremente el estado y los resultados | Usar la acción `/cerrar` o DELETE para anular; resultados y metadatos los calcula el backend. |
| Entradas/salidas sin clasificación o moneda explícita | Enviar categoría, tipo, moneda, monto y medio conocido. |
| Capturar un único total físico | Crear un conteo por moneda y custodias por documento; servidor calcula el contado combinado. |
| Repetir POST para guardar un conteo | Consultar/conservar su ID y usar PATCH; solo hay una fila por jornada/moneda. |
| Copiar o recrear cheques al iniciar el día | El backend arrastra identidades de custodia; no duplicar operación ni ganancia. |
| Usar último precio para recalcular historia | Respetar la referencia del arqueo y las tasas aplicadas de cada operación. |
| Deserializar todas las respuestas como `data` | Respetar las claves exactas y las diferencias entre lista/objeto. |
| Tratar todo decimal como número JSON/double | Aceptar cadenas decimales, usar decimal y conservar hasta 8 decimales cuando corresponde. |
| Convertir null a cero | Representar «pendiente» y exigir captura explícita antes de cerrar. |
| Sumar todas las mesas históricas al arqueo | El resumen usa operaciones de caja; enlazar una mesa compatible sin modificarla ni duplicar el hecho. |
| Asumir ingresos por DHL o número de servicios | Mantener registros auxiliares sin crear movimientos monetarios automáticos. |
| Cargar solamente la primera página | Recorrer `Paginacion` o usar la extracción específica de una jornada. |
| Permitir guardar después del cierre | Bloquear captura financiera y ofrecer únicamente seguimientos permitidos. |
| Reenviar automáticamente toda petición fallida | Distinguir fallo de validación de resultado incierto por red y reconciliar antes de repetir. |

No existe un endpoint universal `guardarArqueoCompleto` que persista todos los formularios en una sola petición. Una implementación con pestañas puede guardar cada recurso por separado y mantener visible el estado de sus peticiones pendientes.

## 19. Comprobaciones de aceptación del cliente

Ejecutar estos casos con una base de pruebas cuya estructura ya corresponda a esta versión:

| Caso | Resultado esperado |
| --- | --- |
| Login válido y consulta autenticada | Se obtiene token y se consultan los datos visibles. |
| Token ausente o revocado | Se maneja 401 sin intentar deserializar un arqueo. |
| Usuario inactivo / cajero administra catálogo | Se maneja 403. |
| Consultar arqueo de otro cajero | No se muestran sus datos; habitualmente 404. |
| Primera apertura sin saldo o motivo | 422 con error asociado al campo. |
| Abrir otra jornada del mismo cajero o de la misma caja ocupada | Error sin crear otro registro. |
| Totales no informados frente a totales cero | Pendientes/null frente a valores declarados, diferenciados en pantalla. |
| Actualizar solo un total | Los demás conservan sus valores. |
| Gasto sin concepto / movimiento mixto sin suma válida | 422. |
| Compra 5.83 a 36.5 sin NIO explícito | Se conserva `212.79500000`. |
| Conteo borrador / segunda fila de misma moneda | No permite cerrar / rechaza el duplicado. |
| USD con piezas de 1000 o NIO con piezas de 2 | 422 si la cantidad no es cero. |
| Cheque de ganancia sin principal | Se conserva monto null; no puede incluirse en custodia. |
| Componente inicial de cheques conocido sin sus documentos | El cierre devuelve 422 aunque el resumen no tenga pendientes básicos. |
| Diferencia distinta de cero | Se muestra por moneda y se puede cerrar tras confirmar. |
| Cierre sin `confirmar_cheques` / cierre correcto | 422 / resultados finales y estado cerrado. |
| Editar o anular movimiento de jornada cerrada | Rechazado; la interfaz conserva el estado anterior. |
| Nuevo día después del cierre | Saldo inicial = contado final y documentos arrastrados una sola vez. |
| Seguimiento de DHL cerrado | Permite campos de seguimiento; rechaza guía o detalles. |
| Guía o enrolamiento repetido | Puede aceptarse; el consumidor detecta y presenta la posible repetición. |
| Más de 100 registros en una lista | Se recorren páginas sin omisiones. |
| Timeout después de un POST o cierre | No se duplica al recuperar la pantalla; se consulta el estado real. |

La verificación del cliente debe añadir una prueba de autenticación Sanctum contra un servidor de pruebas. Las pruebas aisladas del repositorio usan usuarios ficticios y SQLite en memoria para reglas de negocio; no prueban conectividad del escritorio ni tokens reales de un despliegue.

Validación realizada para esta guía: los 25 bloques JSON se analizaron correctamente; los dos bloques C# se compilaron juntos con SDK .NET 9 y lenguaje C# 10, sin advertencias, y superaron 96 comprobaciones con HTTP simulado. Los cuerpos JSON del ejemplo continuo se ejecutaron contra las rutas/controladores reales con SQLite en memoria: 38 comprobaciones de apertura, resumen, cierre, continuidad, extracción y conservación de los maestros ficticios. No se conectó al servidor publicado ni se ejecutaron migraciones sobre la base real. La compatibilidad con el framework y la arquitectura concretos del escritorio debe verificarse allí.

## 20. Referencias locales y entrega al otro proyecto

Fuentes del contrato en este repositorio:

- [Rutas HTTP](../routes/api.php).
- [ArqueoController](../app/Http/Controllers/Api/ArqueoController.php) y [ArqueoService](../app/Services/ArqueoService.php): apertura, resumen y cierre.
- [RegistroCajaController](../app/Http/Controllers/Api/RegistroCajaController.php): envolturas, filtros, permisos, validación y persistencia comunes.
- [Controladores por recurso](../app/Http/Controllers/Api/).
- [Modelo Arqueo](../app/Models/Arqueo.php) y [denominaciones](../app/Support/Caja/ConteoEfectivo.php).
- [Autenticación existente](../app/Http/Controllers/AuthController.php).
- [Guía breve del backend](API_CAJA.md) y [comparativa con los Excel](REGISTROS_CAJA.md).
- [Pruebas de contratos/reglas](../tests/Schema/caja_api.php) y [pruebas del esquema](../tests/Schema/caja_migrations.php).

Este archivo puede adjuntarse al chat del sistema de escritorio como contexto completo del módulo. La dirección del servidor, la versión .NET, la librería JSON y los nombres de formularios/servicios existentes deben obtenerse de ese proyecto, sin inventarlos desde el backend.

Indicaciones para quien implemente allí: conservar la arquitectura y autenticación existentes; adaptar DTOs, servicios y pantallas a este contrato y, para los procesos administrativos, a API_ADMINISTRACION.md; utilizar los identificadores reales del backend; implementar recuperación de jornada abierta, continuidad, captura por recurso y confirmación de cierre; no ejecutar modificaciones directas sobre las tablas protegidas. Cualquier regla nueva de redondeo, reapertura, centavos físicos o importación requiere una ampliación coordinada del contrato.

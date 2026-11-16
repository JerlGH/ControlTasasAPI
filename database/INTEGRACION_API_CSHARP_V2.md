# Integración completa de ControlTasasAPI con C# — versión 2

**Referencia vigente:** [INTEGRACION_API_CSHARP_V4.md](INTEGRACION_API_CSHARP_V4.md) reúne este contrato, Libro Diario, servicios externos y las correcciones posteriores en un solo archivo. Para la integración C# utilizar V4. Este documento se conserva como antecedente de V2.

**Contrato de entrega: 5 de noviembre de 2026.** Destino: chat/proyecto «Ggrupo Systems - Control de Caja Wester - Sistema escritorio».

**Ampliación posterior de esta misma fecha:** el [contrato del Libro Diario](API_LIBRO_DIARIO.md) incorpora siete recursos nuevos, revisiones/cierres y el campo opcional `arqueosmadre.id_libro_diario`. Añade también la conexión de sus seis saldos con el control financiero. Para consumir esta ampliación, utilizar ambas guías; el resto de los contratos de esta entrega conserva su funcionamiento.

Este documento sustituye la entrega anterior como referencia principal de integración. Reúne en un solo archivo el contrato individual de caja vigente, los 30 recursos administrativos, las cuentas personales, las diferencias respecto a la versión anterior y ejemplos de cliente C#. Los apéndices forman parte de este documento; no hace falta disponer de las conversaciones ni adjuntar las guías anteriores para entender los contratos.

El contenido describe el código del repositorio, no confirma la versión de un servidor publicado. No se ejecutaron migraciones ni se modificaron datos de producción durante esta entrega. Los ejemplos utilizan datos ficticios y requieren sustituir sus IDs por los devueltos por la API.

## 1. Cómo utilizar esta entrega

1. Revisar primero los cambios de las secciones 2–5 y las limitaciones de la sección 6.
2. Conservar el cliente de caja ya integrado donde su contrato siga vigente; añadir el cliente administrativo de la sección 7 o adaptar su equivalente en la arquitectura existente.
3. Implementar los procesos siguiendo el apéndice B; para fondos y préstamos personales seguir también el apéndice C.
4. Usar los campos y tipos del catálogo del apéndice D para completar DTOs y formularios. Los nombres JSON tienen que respetarse exactamente.
5. Ejecutar los casos de aceptación de la sección 8 contra un servidor de pruebas con este esquema. Las pruebas en memoria del backend no verifican conectividad ni login real del escritorio.

Accesos: [cambios](#cambios-v2), [respuestas y tipos](#contratos-v2), [acciones](#acciones-v2), [límites](#limites-v2), [cliente C#](#cliente-v2), [aceptación](#aceptacion-v2), [caja](#referencia-caja), [administración](#referencia-administracion), [cuentas personales](#referencia-personales), [tipos de respuesta](#catalogo-respuestas).

<a id="cambios-v2"></a>
## 2. Diferencias que debe incorporar el escritorio

La referencia anterior se centraba en la jornada individual del cajero. Esa jornada sigue existiendo. La ampliación agrega una capa administrativa que lee jornadas, clasifica dinero y administra saldos y cierres de grupo.

| Área / supuesto de la entrega anterior | Contrato actual | Cambio concreto en C# |
| --- | --- | --- |
| Solo registros individuales de caja | También existe `/api/administracion/*`. | Añadir navegación y servicios administrativos sin cambiar las rutas individuales. |
| Claves particulares como `Arqueo`, `Envio`, `Entrada salida` | Se conservan en caja; administración usa `Registro`, `Registros` y `Resultado`. | Separar envolturas. No intentar leer una respuesta administrativa como `Arqueo` ni buscar `data` universal. |
| Usuario cajero accede a sus registros | Todos los endpoints administrativos, también GET, exigen administrador activo. | Ocultar o deshabilitar módulos administrativos para cajeros y manejar 403. |
| Monedas de captura de caja admiten alias | Administración admite exactamente `NIO` y `USD`. | Enviar valores canónicos en formularios y filtros nuevos. |
| Importes habituales de caja con dos decimales | Los importes administrativos admiten ocho; tasas seis; cantidades de activos/obra seis. | Conservar precisión y enviar cadenas con punto. No redondear los datos antes de guardarlos. |
| Todos los saldos se podrían modelar como `decimal` | Los saldos personales guardados son `DECIMAL(32,8)` y los agregados pueden superar la precisión de `System.Decimal`. | Mantener esos saldos y agregados como cadenas o JSON, o implementar una conversión exacta validada; no usar `double`. |
| Cierre individual equivale al fin de todo el proceso | Existen cierre de caja, consolidado madre, confirmación de matriz, liquidación y cierre financiero. | Mostrar estados y acciones independientes; no intercambiar sus IDs. |
| Consolidación no implementada | `arqueosmadre` conserva fecha, alcance, usuarios previstos, fuentes y versiones. | Capturar la cobertura explícita y presentar ausentes/abiertos como pendientes. |
| Totales WU/Biller solo individuales | Se comparan cuatro totales con `reportesmatriz`/`conciliaciones`. | Implementar diferencias por servicio y moneda; revisar no borra la diferencia. |
| Pago y clasificación podrían ser el mismo POST | `operaciones` registra/reconoce dinero; `aplicaciones` y `movimientospersonales` clasifican ese dinero. | Guardar el ID de la operación y reutilizarlo; no volver a mover banco/caja al aplicar. |
| Se podría reconocer dinero de una jornada abierta | Los enlaces financieros a caja exigen jornada cerrada y fuente vigente. | Esperar al cierre para reconocer ese origen administrativo. No volver a editar la salida/entrada física. |
| Una operación solo se usaría en aplicaciones | Su disponibilidad también descuenta movimientos personales; hay reservas para matriz, anticipos y financiamientos. | Refrescar datos y manejar rechazo por dinero ya utilizado aunque el formulario mostrara un importe disponible. |
| `id_cargo` solo admitía cargos manuales | También admite cargos generados por préstamos personales vigentes. | Para cobrar un préstamo usar su `id_movimiento_generado`; los demás cargos automáticos mantienen destino específico. |
| Un fondo solo podría consumirse hasta cero | `movimientospersonales` permite retiros excedidos y reintegros. | Mostrar fondos del titular y deuda del titular en la misma cuenta, sin cambiar su tipo. |
| `cuentas/{id}/saldo` solo muestra saldo/movimientos | Para `fondo` agrega `posicion_personal`. | Añadir `fondos_del_titular` y `deuda_del_titular`; el saldo puede ser negativo. |
| Se podrían ajustar libremente antecedentes de cuenta | Movimientos personales posteriores protegen sus saldos anteriores. | Rechazar correcciones retroactivas y resolver dependencias en orden inverso antes de reclasificar. |
| Un depósito declarado ya está acreditado | `depositosliquidacion` separa pendiente y acreditado, con cuenta de tránsito. | Mostrar ambos estados y acreditar con fecha real, incluso en un periodo posterior. |
| Sueldo, anticipo y retención se registrarían como gastos equivalentes | Las obligaciones generan cargos y algunos gastos; las compensaciones no generan dinero. | Separar salario bruto, anticipo, descuento de anticipo, retención INSS laboral y pago neto. |
| Una cuota reduciría toda la deuda por su importe | Solo su componente capital reduce principal; interés/seguro/comisión generan gasto financiero. | En un pago parcial capturar los cuatro componentes. |
| DELETE podría eliminar datos o devolver dinero | En administración anula/desactiva con motivo; no revierte el dinero real de la operación. | Ofrecer anulación y devolución como procesos distintos; no inventar un egreso/ingreso al anular una clasificación. |
| El Excel «GRAN TOTAL USD» representaría efectivo disponible | Se expone como `capital_grupo_ajustado_usd`, con componentes y fuentes. | Etiquetarlo como control de capital ajustado, separado de efectivo, utilidad y capital de trabajo. |
| El servidor estaría ya actualizado | El contrato requiere las migraciones de esta versión. | Coordinar despliegue y pruebas del servidor; no aplicar migraciones desde el escritorio. |

El módulo de caja no fue renombrado ni reemplazado por la expansión administrativa. Los campos de apertura, conteos, continuidad, custodia de cheques, tasas, DHL y enrolamientos siguen descritos íntegramente en el apéndice A. Los cambios recientes se concentran en los recursos administrativos y su conexión con esas fuentes.

## 3. Módulos y orden funcional

| Módulo | Recursos relativos a `/api/administracion` | Flujo que debe implementar la interfaz |
| --- | --- | --- |
| Catálogos y evidencia | `contrapartes`, `documentos`, `centroscosto`, `cuentas` | Crear titular/centro/cuenta; los documentos son metadatos, no carga de archivos. |
| Cuentas y dinero | `operaciones`, `movimientoscuenta`, `aplicacionescuenta`, `aplicaciones` | Dinero real, ajustes sin dinero, cruce de partidas y aplicación a documentos. |
| Cuentas personales | `movimientospersonales` | Depósito, retiro, reintegro y préstamo entregado; cobro posterior mediante `aplicaciones`. |
| Consolidación | `arqueosmadre` | Elegir fecha, tasa y usuarios previstos; consultar, recalcular y cerrar. |
| Matriz | `reportesmatriz`, `detallesreporte`, `conciliaciones` | Capturar cuatro líneas, confirmar, comparar y revisar diferencias si corresponde. |
| Liquidación | `liquidaciones`, `depositosliquidacion` | Arrastre, anticipos, depósitos, acreditación y cierre. |
| Gastos | `gastos` | Reconocer gasto y, opcionalmente, su CxP; aplicar su pago sin duplicar el cargo. |
| Laboral | `obligacioneslaborales`, `compensacioneslaborales` | Obligaciones, anticipos, descuentos/retenciones y pagos. |
| Comisiones | `comisiones` | Devengado por cobrar/pagar y cobros/pagos parciales. |
| Construcción | `proyectos`, `movimientosobra`, `aplicacionesobra` | Costos, anticipos pagados, su aplicación, recuperaciones y devoluciones. |
| Activos | `activos`, `eventosactivos` | Adquisición/cantidad/costo y eventos de traslado o baja. |
| Financiamiento recibido | `financiamientos`, `cuotas` | Deuda, desembolso y calendario pactado con componentes. |
| Incidencias | `incidencias`, `gestionesincidencia` | Solicitud/confirmación separadas del reembolso o pago efectivo. |
| Control y auditoría | `capital`, `cierres`, `historial` | Revisar informe, confirmar fuentes, cerrar y consultar evidencia. `capital` es consulta, no un recurso CRUD. |

Un préstamo **entregado** a un particular usa `movimientospersonales` y CxC. Un financiamiento **recibido** usa `financiamientos` y CxP. No reutilizar el módulo de financiamiento recibido invirtiendo sus signos para representar un préstamo entregado.

<a id="contratos-v2"></a>
## 4. Transporte, envolturas y tipos

La dirección base del cliente debe ser la URL real de la API terminada en `/api/`, con barra final. Las rutas relativas nuevas empiezan por `administracion/`, sin barra inicial para no perder un eventual subdirectorio del servidor. No hay endpoint de versión/capacidades: `v2` es la revisión de este documento, no un prefijo HTTP ni un campo que deba enviarse.

La autenticación sigue siendo `POST login` con `usuario` y `contraseña`; usar `access_token` como Bearer. No hay otro inicio de sesión para administración. Los ejemplos C# del apartado 7 reutilizan el mismo `HttpClient` autenticado y no modifican su token.

| Operación administrativa | Código HTTP normal | Clave JSON que contiene los datos |
| --- | --- | --- |
| GET lista | 200 | `Registros` (array) y `Paginacion`. |
| GET detalle | 200 | `Registro` (objeto). |
| POST alta | 201 | `Registro` (objeto). |
| PATCH/PUT | 200 | `Registro` (objeto). |
| DELETE con motivo | 200 | `Registro` con estado resultante. |
| GET/POST de acción y GET capital | 200 | `Resultado`; su estructura depende de la acción. |

```json
{
  "Registros": [],
  "Paginacion": { "total": 0, "pagina": 1, "por_pagina": 50 },
  "Status": 200
}
```

En administración, los listados aceptan `page`, `per_page` (1–100), `fecha_desde`, `fecha_hasta`, los campos `id_*` del modelo y ciertos campos de igualdad como estado/tipo/moneda/código/referencia cuando existen. Los filtros desconocidos pueden ignorarse: no interpretar que un parámetro inventado se aplicó. Los registros anulados se incluyen si no se filtra el estado. No hay filtro universal `fecha` para todos los listados; usar `fecha_desde`/`fecha_hasta` o los parámetros específicos de las acciones.

`Paginacion` no incluye un enlace `next` ni `last_page`: avanzar `page` mientras `pagina × por_pagina < total`. La ordenación de listas es ID descendente; el estado de cuenta se ordena por fecha e ID. Las respuestas no cargan automáticamente todos los objetos relacionados: una FK no sustituye consultar su catálogo o el resumen correspondiente.

Los IDs se modelan como `long`/`long?`; referencias, números de cuenta, guías y códigos como `string`. Los campos de negocio de fecha se mantienen como `yyyy-MM-dd`; timestamps con cast explícito pueden ser `yyyy-MM-dd HH:mm:ss`, mientras `created_at`/`updated_at` pueden estar en ISO 8601. Respetar la configuración horaria del servidor; no convertir la fecha operativa a partir del timestamp técnico.

`DECIMAL(24,8)` y tasas `DECIMAL(12,6)` caben en `System.Decimal`, conservando los valores admitidos. **`saldo_anterior` y `saldo_posterior` de movimientos personales usan `DECIMAL(32,8)`**: mantenerlos como `string` si se pretende admitir toda la precisión del servidor. También se recomienda conservar como texto los saldos calculados y agregados financieros, que no tienen necesariamente el mismo límite que una sola captura. Un `TryParse` exitoso no garantiza por sí solo que no se haya redondeado una precisión superior. El cliente no necesita recalcular estos resultados para guardarlos.

Omitir una propiedad en PATCH conserva su valor; `null` solo limpia campos que lo admitan; cero es un dato declarado. No reenviar un DTO completo de lectura como escritura: los campos calculados, actor, IDs de origen internos y estados de cierre se rechazan. La API administrativa valida monedas canónicas y tipos en minúsculas; no trasladar la normalización de alias de caja a estos recursos.

Los errores de reglas/validación son 422 y usan `errors`; login conserva errores históricos `Mensaje`/`Errores` y puede devolver 400/401/404. Una restricción de integridad o clave duplicada puede devolver 409. El formato exacto exterior de errores depende del manejador Laravel del servidor; leer `message`, `Mensaje` o `error` cuando estén presentes, y usar el código HTTP. No deducir un fallo de red de un 422 ni reintentar automáticamente POST/DELETE.

Los listados administrativos no implementan un filtro directo `clave`. Para resolver un resultado incierto, filtrar por los IDs/fechas admitidos, recorrer las páginas y comparar la clave conocida en los registros devueltos. No enviar `?clave=...` y asumir que el primer registro corresponde a esa captura: el parámetro puede ignorarse.

<a id="acciones-v2"></a>
## 5. Cambios de proceso y formatos de acciones

### 5.1 Estados y acciones no intercambiables

| Estado/proceso | Acción o lectura | Datos bajo `Resultado` |
| --- | --- | --- |
| Consolidado madre en borrador | GET `arqueosmadre/{id}/resumen`; POST `.../recalcular`; POST `.../cerrar` | `Registro`, `Totales`, `Fuentes`. Las fuentes del borrador y las filas persistidas de un cerrado pueden contener campos adicionales. |
| Reporte de matriz | POST `reportesmatriz/{id}/confirmar` | El reporte directamente, no otro `Registro`. |
| Conciliación con diferencias | POST `conciliaciones/{id}/revisar` con motivo | La conciliación directamente, conservando `resultado`. |
| Liquidación | GET `liquidaciones/{id}/resumen`; POST `.../cerrar` | `Registro`, `Resumen`. |
| Depósito pendiente de matriz | POST `depositosliquidacion/{id}/acreditar` | El depósito actualizado directamente. |
| Cuenta financiera | GET `cuentas/{id}/saldo?fecha=...` | `Cuenta`, `fecha_corte`, `saldo`, `movimientos`; para fondos, `posicion_personal`. |
| Activo | GET `activos/{id}/inventario?fecha=...` | `Registro`, `fecha_corte`, `ubicaciones`; las claves son IDs de agencia como texto, y `0` significa sin agencia. |
| Proyecto | GET `proyectos/{id}/resumen?fecha=...` | `Registro`, `fecha_corte`, `Totales`. |
| Financiamiento | GET `financiamientos/{id}/resumen?fecha=...` | `Registro`, `Cuenta`, `Cuotas`; el calendario se devuelve completo. |
| Incidencia | GET `incidencias/{id}/resumen?fecha=...` | `Registro`, `Pendiente`, `Gestiones`; la ficha tiene gestión actual y el historial se filtra al corte. |
| Capital | GET `capital` con fecha/periodo/madre/liquidación | Componentes, fuentes, indicadores, pendientes, cuentas excluidas y versión de fórmula. |

El alta de un cierre financiero es POST `cierres`, por lo que su respuesta utiliza **`Registro`**, no `Resultado`. No hay una acción `cierres/{id}/cerrar` ni reapertura financiera.

### 5.2 Dinero único y destinos

Una `operacion` financiera representa el dinero. Crear `aplicaciones` o `movimientospersonales` después no crea otra entrada/salida bancaria o de caja. La disponibilidad se comparte entre ambas clasificaciones y las reservas específicas; no existe un campo/endpoint público universal llamado `saldo_disponible_operacion`. Los formularios pueden mostrar una estimación consultando sus asignaciones, pero el servidor decide si el importe continúa disponible al guardar.

El reconocimiento de caja guarda `origen_tipo` + `id_origen` y debe coincidir con la moneda/importe de una fuente vigente de un arqueo cerrado. Solo un reconocimiento activo por fuente/pata. En una operación de caja que ya representa ingreso/egreso no se agrega otra cuenta bancaria; un traslado de caja/banco utiliza solamente la contraparte bancaria correspondiente. No elegir una mesa histórica cualquiera para fabricar un nuevo origen.

Una aplicación indica exactamente un destino. Un `id_cargo` de préstamo personal es el `id_movimiento_generado` de su alta; el ID del registro `movimientospersonales` no es el ID del cargo. Las aplicaciones parciales conservan importe en moneda de origen y `monto_destino`. Para un movimiento personal, el equivalente se llama **`monto_cuenta`** y se acompaña de `moneda_origen`/`moneda`.

### 5.3 Fondos personales y préstamos

La pantalla de un fondo debe distinguir su saldo actual de los desgloses históricos de cada movimiento. `deuda_generada` y `saldo_posterior` describen esa captura, no el pendiente actual después de todos los movimientos siguientes. Consultar el saldo y sus dos campos de posición para la situación actual.

| Secuencia de fondo NIO | Efecto esperado |
| --- | --- |
| Depósito 100 desde cero | Fondo 100. |
| Retiro 150 | Consume fondos 100 y genera deuda 50; saldo −50. |
| Depósito 20 | Cancela deuda 20; saldo −30. |
| Depósito 50 | Cancela deuda 30 y genera fondos 20; saldo 20. |

Una CxC para préstamo entregado debe tener saldo no negativo al entregar un nuevo préstamo; regularizar previamente un saldo agregado a favor. El principal genera un cargo individual. Su cobro usa una operación de ingreso y `aplicaciones.id_cargo`. No hay generación automática de intereses ni calendario de cobro en este proceso.

La cronología y las dependencias también afectan a endpoints existentes: un ajuste manual o una anulación de abono financiero pueden rechazarse si alterarían el saldo anterior de un movimiento personal posterior de esa cuenta. Presentar la razón del servidor y resolver primero los registros posteriores en un periodo abierto.

### 5.4 Liquidación, nómina y cierres

La primera liquidación exige saldo anterior explícito y motivo; las siguientes heredan el negativo del pendiente de su cierre anterior, del mismo alcance. Un depósito pendiente de matriz usa CxC de tránsito; acreditarlo genera su abono y no otro egreso. Puede acreditarse después del cierre de su liquidación en fecha de periodo financiero abierto; el siguiente corte reconoce esa acreditación y el cierre anterior conserva su fotografía.

Una obligación salarial reconocida puede generar gasto automático; su pago no vuelve a ser gasto. Un anticipo laboral usa CxC y puede reservar su egreso mediante `id_operacion_anticipo`. Una compensación de anticipo reduce CxC y CxP sin dinero nuevo. Una retención INSS laboral utiliza `id_retencion` en lugar de `id_anticipo`: reduce el salario pendiente y conserva la deuda de INSS para pagarla posteriormente.

El cierre financiero utiliza consolidado de grupo completo/cerrado, liquidación cerrada del mismo corte, última conciliación conciliada/revisada, periodos de gasto resueltos, motivo y confirmación explícita de fuentes. Su protección temporal se aplica a las escrituras administrativas. El módulo individual mantiene sus propias reglas de jornada y continuidad: no asumir que un cierre financiero es una acción global que cierra todas las cajas o cambia estados de los maestros. La fotografía cerrada nunca se recalcula al consultar fuentes posteriores.

<a id="limites-v2"></a>
## 6. Limitaciones conocidas que no debe ocultar el cliente

**Reducciones de obligaciones automáticas corregidas en la segunda revisión del Libro Diario.** El pago/cobro de un cargo o documento con cargo asociado verifica su pendiente y el saldo neto de la cuenta a la fecha, incluyendo movimientos posteriores existentes. Una deuda de 100 con reducción manual de 20 ya no admite pagar 100. Para imputar la reducción, crear el abono manual con motivo y cruzarlo con el cargo manual o automático mediante `aplicacionescuenta`; después, pagar 80 cancela saldo y pendiente. El ajuste no genera dinero ni modifica automáticamente el importe nominal del gasto. Los abonos automáticos siguen administrándose desde su origen y las aplicaciones monetarias conservan sus destinos específicos. Consultar [la revisión](REVISION_LIBRO_DIARIO.md).

Otras condiciones de alcance:

- Un `saldo_inicial` agregado de CxC/CxP no crea partidas cobrables/pagables. Si se necesitan aplicaciones, usar apertura cero y cargos identificados o el desglose real, evitando duplicar inicial y cargo. Los fondos personales sí consideran su saldo inicial para retiro/reintegro.
- No hay compensación personal general entre cuentas ni estado conjunto nativo por persona; se listan sus cuentas y se consulta cada saldo, separado por moneda. La compensación laboral es específica.
- Los documentos son metadatos (`ubicacion`, hash, referencia), no carga/descarga de archivos. No hay importador de Excel ni sincronización con AirPak o bancos.
- Los reportes de matriz se capturan como cuatro totales. La comparación no identifica la transacción causante de una diferencia.
- No hay nómina legal automática, depreciación automática, cálculo general de intereses, amortización automática ni reprogramación de cuotas después de un abono extraordinario.
- No hay endpoint para reabrir cierres financieros, guardar todas las pestañas en una petición, consultar versión del servidor o garantizar idempotencia de todas las escrituras.
- Las rutas heredadas de maestros y mesas no se modernizaron en este trabajo. No asumir que comparten filtros, errores o permisos de administración; consultar sus contratos reales si el escritorio ya las usa.

Estos límites no impiden utilizar depósitos, retiros excedidos, reintegros y préstamos personales ya implementados. Las funciones pendientes deben conservarse como pendientes en la planificación del escritorio.

<a id="cliente-v2"></a>
## 7. Cliente C# administrativo y DTOs de referencia

El ejemplo utiliza C# 10 y APIs de .NET 6 o posterior, sin paquetes externos. No fija la versión real del escritorio: si usa .NET Framework o Newtonsoft.Json, conservar los nombres y las envolturas y adaptar el transporte a su arquitectura. Reutilizar el `HttpClient` con su dirección base `/api/` y Bearer ya configurados por la sesión; no crear otro login ni token.

Los DTOs son de lectura. Las escrituras usan objetos o DTOs específicos con solo campos admitidos. Los saldos calculados se mantienen como texto y los bloques variables como `JsonElement`; el catálogo del apéndice D permite tipar más recursos después.

Guardar como ApiAdministracionClient.cs o adaptar al servicio HTTP existente:

```csharp
#nullable enable
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;

namespace ControlCaja.Integracion
{
    public sealed class ApiAdministracionException : Exception
    {
        public HttpStatusCode HttpStatus { get; }
        public Dictionary<string, string[]> Errores { get; }
        public string CuerpoOriginal { get; }

        public ApiAdministracionException(HttpStatusCode status, string mensaje,
            Dictionary<string, string[]> errores, string cuerpo) : base(mensaje)
        {
            HttpStatus = status;
            Errores = errores;
            CuerpoOriginal = cuerpo;
        }
    }

    public sealed class PaginaAdministrativa
    {
        [JsonPropertyName("total")] public long Total { get; set; }
        [JsonPropertyName("pagina")] public int Pagina { get; set; }
        [JsonPropertyName("por_pagina")] public int PorPagina { get; set; }
    }

    public sealed class ListaAdministrativa<T>
    {
        [JsonPropertyName("Registros")] public List<T> Registros { get; set; } = new();
        [JsonPropertyName("Paginacion")] public PaginaAdministrativa Paginacion { get; set; } = new();
        [JsonPropertyName("Status")] public int Status { get; set; }
    }

    public sealed class CuentaAdministrativaDto
    {
        [JsonPropertyName("id")] public long Id { get; set; }
        [JsonPropertyName("codigo")] public string Codigo { get; set; } = "";
        [JsonPropertyName("nombre")] public string Nombre { get; set; } = "";
        [JsonPropertyName("tipo")] public string Tipo { get; set; } = "";
        [JsonPropertyName("moneda")] public string Moneda { get; set; } = "";
        [JsonPropertyName("id_contraparte")] public long? IdContraparte { get; set; }
        [JsonPropertyName("saldo_inicial")] public decimal SaldoInicial { get; set; }
        [JsonExtensionData] public Dictionary<string, JsonElement>? Otros { get; set; }
    }

    public sealed class PosicionPersonalDto
    {
        [JsonPropertyName("fondos_del_titular")] public string FondosDelTitular { get; set; } = "";
        [JsonPropertyName("deuda_del_titular")] public string DeudaDelTitular { get; set; } = "";
    }

    public sealed class EstadoCuentaAdministrativaDto
    {
        [JsonPropertyName("Cuenta")] public CuentaAdministrativaDto Cuenta { get; set; } = new();
        [JsonPropertyName("fecha_corte")] public string FechaCorte { get; set; } = "";
        [JsonPropertyName("saldo")] public string Saldo { get; set; } = "";
        [JsonPropertyName("movimientos")] public List<JsonElement> Movimientos { get; set; } = new();
        [JsonPropertyName("posicion_personal")] public PosicionPersonalDto? PosicionPersonal { get; set; }
    }

    public sealed class MovimientoPersonalDto
    {
        [JsonPropertyName("id")] public long Id { get; set; }
        [JsonPropertyName("clave")] public string Clave { get; set; } = "";
        [JsonPropertyName("id_cuenta")] public long IdCuenta { get; set; }
        [JsonPropertyName("id_operacion")] public long IdOperacion { get; set; }
        [JsonPropertyName("fecha")] public string Fecha { get; set; } = "";
        [JsonPropertyName("tipo")] public string Tipo { get; set; } = "";
        [JsonPropertyName("monto")] public decimal Monto { get; set; }
        [JsonPropertyName("moneda_origen")] public string MonedaOrigen { get; set; } = "";
        [JsonPropertyName("tasa_cambio")] public decimal? TasaCambio { get; set; }
        [JsonPropertyName("moneda")] public string Moneda { get; set; } = "";
        [JsonPropertyName("monto_cuenta")] public decimal MontoCuenta { get; set; }
        [JsonPropertyName("saldo_anterior")] public string SaldoAnterior { get; set; } = "";
        [JsonPropertyName("saldo_posterior")] public string SaldoPosterior { get; set; } = "";
        [JsonPropertyName("fondos_utilizados")] public decimal FondosUtilizados { get; set; }
        [JsonPropertyName("fondos_incrementados")] public decimal FondosIncrementados { get; set; }
        [JsonPropertyName("deuda_generada")] public decimal DeudaGenerada { get; set; }
        [JsonPropertyName("deuda_cancelada")] public decimal DeudaCancelada { get; set; }
        [JsonPropertyName("id_movimiento_generado")] public long? IdMovimientoGenerado { get; set; }
        [JsonPropertyName("estado")] public string Estado { get; set; } = "";
        [JsonExtensionData] public Dictionary<string, JsonElement>? Otros { get; set; }
    }

    public sealed class CoberturaConsolidacionDto
    {
        [JsonPropertyName("esperados")] public int Esperados { get; set; }
        [JsonPropertyName("recibidos")] public int Recibidos { get; set; }
        [JsonPropertyName("cerrados")] public int Cerrados { get; set; }
    }

    public sealed class TotalesConsolidacionDto
    {
        [JsonPropertyName("completo")] public bool Completo { get; set; }
        [JsonPropertyName("cobertura")] public CoberturaConsolidacionDto Cobertura { get; set; } = new();
        [JsonPropertyName("metricas")] public Dictionary<string, JsonElement> Metricas { get; set; } = new();
        [JsonPropertyName("pendientes")] public List<JsonElement> Pendientes { get; set; } = new();
        [JsonPropertyName("por_agencia")] public List<JsonElement> PorAgencia { get; set; } = new();
    }

    public sealed class ResultadoConsolidacionDto
    {
        [JsonPropertyName("Registro")] public JsonElement Registro { get; set; }
        [JsonPropertyName("Totales")] public TotalesConsolidacionDto Totales { get; set; } = new();
        [JsonPropertyName("Fuentes")] public List<JsonElement> Fuentes { get; set; } = new();
    }

    public sealed class ApiAdministracionClient
    {
        private readonly HttpClient http;
        private readonly JsonSerializerOptions json = new()
        {
            PropertyNameCaseInsensitive = false,
            NumberHandling = JsonNumberHandling.AllowReadingFromString
        };

        public ApiAdministracionClient(HttpClient clienteAutenticado)
        {
            http = clienteAutenticado ?? throw new ArgumentNullException(nameof(clienteAutenticado));
            if (http.BaseAddress is null || !http.BaseAddress.IsAbsoluteUri ||
                !http.BaseAddress.AbsoluteUri.EndsWith("/", StringComparison.Ordinal))
                throw new ArgumentException("Configure la direccion base real terminada en /api/.");
        }

        public async Task<ListaAdministrativa<T>> ListarAsync<T>(string recurso,
            IReadOnlyDictionary<string, string?>? filtros = null, CancellationToken ct = default)
        {
            string body = await EnviarAsync(HttpMethod.Get, Ruta(recurso) + Consulta(filtros), null, ct).ConfigureAwait(false);
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("Registros", out var rows) || rows.ValueKind != JsonValueKind.Array ||
                !doc.RootElement.TryGetProperty("Paginacion", out var page) || page.ValueKind != JsonValueKind.Object)
                throw new JsonException("La lista administrativa no contiene Registros/Paginacion.");
            return Deserializar<ListaAdministrativa<T>>(body);
        }

        public async Task<T> ObtenerAsync<T>(string recurso, long id, CancellationToken ct = default) =>
            Extraer<T>(await EnviarAsync(HttpMethod.Get, Ruta(recurso + "/" + id), null, ct).ConfigureAwait(false), "Registro");

        public async Task<T> CrearAsync<T>(string recurso, object cuerpo, CancellationToken ct = default) =>
            Extraer<T>(await EnviarAsync(HttpMethod.Post, Ruta(recurso), cuerpo, ct).ConfigureAwait(false), "Registro");

        public async Task<T> ActualizarAsync<T>(string recurso, long id, object campos, CancellationToken ct = default) =>
            Extraer<T>(await EnviarAsync(HttpMethod.Patch, Ruta(recurso + "/" + id), campos, ct).ConfigureAwait(false), "Registro");

        public async Task<T> AnularAsync<T>(string recurso, long id, string motivo, CancellationToken ct = default) =>
            Extraer<T>(await EnviarAsync(HttpMethod.Delete, Ruta(recurso + "/" + id), new { motivo }, ct).ConfigureAwait(false), "Registro");

        public async Task<T> ConsultarResultadoAsync<T>(string rutaRelativa,
            IReadOnlyDictionary<string, string?>? filtros = null, CancellationToken ct = default) =>
            Extraer<T>(await EnviarAsync(HttpMethod.Get, Ruta(rutaRelativa) + Consulta(filtros), null, ct).ConfigureAwait(false), "Resultado");

        public async Task<T> EjecutarAccionAsync<T>(string recurso, long id, string accion,
            object? cuerpo = null, CancellationToken ct = default) =>
            Extraer<T>(await EnviarAsync(HttpMethod.Post, Ruta(recurso + "/" + id + "/" + accion), cuerpo, ct).ConfigureAwait(false), "Resultado");

        private static string Ruta(string valor)
        {
            if (string.IsNullOrWhiteSpace(valor) || valor.StartsWith("/", StringComparison.Ordinal) ||
                valor.Contains("..", StringComparison.Ordinal) || valor.Contains('?') ||
                valor.Contains('#') || Uri.TryCreate(valor, UriKind.Absolute, out _))
                throw new ArgumentException("Use una ruta relativa administrativa y filtros separados.");
            return "administracion/" + valor;
        }

        private static string Consulta(IReadOnlyDictionary<string, string?>? filtros)
        {
            if (filtros is null || filtros.Count == 0) return "";
            var pares = filtros.Where(p => p.Value is not null)
                .Select(p => Uri.EscapeDataString(p.Key) + "=" + Uri.EscapeDataString(p.Value!));
            string query = string.Join("&", pares);
            return query.Length == 0 ? "" : "?" + query;
        }

        private T Deserializar<T>(string body)
        {
            T? value = JsonSerializer.Deserialize<T>(body, json);
            if (value is null) throw new JsonException("Respuesta JSON sin contenido.");
            return value;
        }

        private T Extraer<T>(string body, string clave)
        {
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty(clave, out var value) ||
                value.ValueKind is JsonValueKind.Null or JsonValueKind.Undefined)
                throw new JsonException("La respuesta no contiene " + clave + ".");
            return Deserializar<T>(value.GetRawText());
        }

        private async Task<string> EnviarAsync(HttpMethod metodo, string ruta, object? cuerpo, CancellationToken ct)
        {
            using var request = new HttpRequestMessage(metodo, ruta);
            request.Headers.Accept.ParseAdd("application/json");
            if (cuerpo is not null)
                request.Content = new StringContent(JsonSerializer.Serialize(cuerpo, json), Encoding.UTF8, "application/json");
            using var response = await http.SendAsync(request, ct).ConfigureAwait(false);
            string body = await response.Content.ReadAsStringAsync(ct).ConfigureAwait(false);
            if (!response.IsSuccessStatusCode) throw CrearError(response.StatusCode, body);
            return body;
        }

        private static ApiAdministracionException CrearError(HttpStatusCode status, string body)
        {
            string message = "HTTP " + (int)status;
            var errors = new Dictionary<string, string[]>();
            try
            {
                using var doc = JsonDocument.Parse(body);
                if (doc.RootElement.ValueKind == JsonValueKind.Object)
                {
                    foreach (string key in new[] { "message", "Mensaje", "error" })
                        if (doc.RootElement.TryGetProperty(key, out var msg) && msg.ValueKind == JsonValueKind.String)
                        { message = msg.GetString() ?? message; break; }
                    foreach (string key in new[] { "errors", "Errores" })
                        if (doc.RootElement.TryGetProperty(key, out var map) && map.ValueKind == JsonValueKind.Object)
                        {
                            foreach (var field in map.EnumerateObject())
                                errors[field.Name] = field.Value.ValueKind == JsonValueKind.Array
                                    ? field.Value.EnumerateArray().Select(v => v.ToString()).ToArray()
                                    : new[] { field.Value.ToString() };
                            break;
                        }
                }
            }
            catch (JsonException) { /* El cuerpo original conserva errores no JSON del servidor. */ }
            return new ApiAdministracionException(status, message, errors, body);
        }
    }
}
```

## 7.1 Uso de las nuevas envolturas

```csharp
#nullable enable
using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace ControlCaja.Integracion
{
    public static class EjemplosAdministracion
    {
        // idOperacionIngreso ya fue registrado/reconocido y guardado por el escritorio.
        public static async Task<MovimientoPersonalDto> ClasificarDepositoAsync(HttpClient http,
            long idFondo, long idOperacionIngreso, string clavePersistida,
            string fecha, string montoOrigen, string? tasaCambio = null, CancellationToken ct = default)
        {
            var api = new ApiAdministracionClient(http);
            return await api.CrearAsync<MovimientoPersonalDto>("movimientospersonales", new
            {
                clave = clavePersistida, id_cuenta = idFondo, id_operacion = idOperacionIngreso,
                fecha, tipo = "deposito", monto = montoOrigen, tasa_cambio = tasaCambio,
                concepto = "Deposito del titular"
            }, ct);
        }

        // Para otra moneda, tasaCambio se expresa como cadena NIO por USD.
        public static async Task<JsonElement> CobrarPrestamoAsync(ApiAdministracionClient api,
            MovimientoPersonalDto prestamo, long idOperacionIngreso, string fecha,
            string montoOrigen, string? tasaCambio = null, CancellationToken ct = default)
        {
            if (prestamo.Tipo != "prestamo_entregado" || prestamo.Estado != "registrado")
                throw new InvalidOperationException("Seleccione un prestamo personal vigente.");
            long cargo = prestamo.IdMovimientoGenerado
                ?? throw new InvalidOperationException("El prestamo no contiene su cargo generado.");
            return await api.CrearAsync<JsonElement>("aplicaciones", new
            {
                id_operacion = idOperacionIngreso, fecha, monto = montoOrigen, tasa_cambio = tasaCambio,
                id_cargo = cargo
            }, ct);
        }

        public static Task<EstadoCuentaAdministrativaDto> ConsultarSaldoAsync(ApiAdministracionClient api,
            long idCuenta, string fecha, CancellationToken ct = default) =>
            api.ConsultarResultadoAsync<EstadoCuentaAdministrativaDto>("cuentas/" + idCuenta + "/saldo",
                new Dictionary<string, string?> { ["fecha"] = fecha }, ct);

        // Invocar por la accion explicita del administrador; no al cargar la pantalla.
        public static Task<ResultadoConsolidacionDto> CerrarConsolidadoAsync(ApiAdministracionClient api,
            long idMadre, CancellationToken ct = default) =>
            api.EjecutarAccionAsync<ResultadoConsolidacionDto>("arqueosmadre", idMadre, "cerrar", null, ct);

        public static Task<ListaAdministrativa<MovimientoPersonalDto>> ListarMovimientosAsync(
            ApiAdministracionClient api, long idCuenta, int pagina, CancellationToken ct = default) =>
            api.ListarAsync<MovimientoPersonalDto>("movimientospersonales", new Dictionary<string, string?>
            {
                ["id_cuenta"] = idCuenta.ToString(System.Globalization.CultureInfo.InvariantCulture),
                ["estado"] = "registrado", ["page"] = pagina.ToString(System.Globalization.CultureInfo.InvariantCulture),
                ["per_page"] = "100"
            }, ct);
    }
}
```

Los ejemplos no ejecutan escrituras por sí solos. No llamar un flujo de demostración automáticamente al abrir una ventana ni usar los importes ilustrativos para inicializar producción. En la interfaz: conservar IDs entre pasos, deshabilitar doble clic durante la petición, esperar con `await`, conservar datos capturados ante validación y reconciliar resultados inciertos de red antes de repetir escrituras.

<a id="aceptacion-v2"></a>
## 8. Aceptación y verificación de la entrega

| Caso de integración | Resultado que debe verificar el escritorio |
| --- | --- |
| Cajero abre administración | 403; la sesión de caja sigue usando sus permisos propios. |
| Crear/consultar/listar un recurso administrativo | Leer `Registro`/`Registros`; respetar paginación. |
| Ejecutar resumen/cierre/confirmación | Leer `Resultado` y su forma específica; no asumir siempre `Resultado.Registro`. |
| Alta de cierre financiero | Leer `Registro`, no una acción inexistente. |
| Moneda alias en administración o campo calculado en POST | Presentar 422 por campo; no continuar con los siguientes pasos. |
| Jornada esperada ausente o abierta | Mostrar pendientes y no permitir el cierre madre. |
| Matriz sin una de cuatro líneas | Confirmación rechazada; un cero explícito cuenta como línea informada. |
| Diferencia revisada | Conservar importe y estado revisado, sin mostrar coincidencia falsa. |
| Primer y segundo día de liquidación | Capturar primer saldo; verificar arrastre negativo automático después. |
| Depósito de matriz no acreditado y acreditación posterior | Conservar tránsito al corte anterior y reconocer una vez en el siguiente. |
| Fondo: 100, retiro 150, reintegro 20, reintegro 50 | Saldos 100, −50, −30 y 20; desgloses correctos en una misma cuenta. |
| Préstamo 300 y cobro 120 | Cuenta/cargo quedan en 180; no se registra otra salida al clasificar. |
| Operación parcialmente usada en personal y aplicaciones | Rechazar cualquier exceso del total compartido. |
| Caja cerrada como origen | Conservar la jornada y sus importes físicos después de clasificar. |
| Anular préstamo con cobros o antecedente con movimiento posterior | Rechazo; no limpiar la pantalla como si se hubiera anulado. |
| Retención de salario bruto 500 por 50 | Salario pendiente 450 e INSS pendiente 50, con gasto salarial 500. |
| Cuota 100 de principal + 13 de costos | Reducir deuda 100; clasificar costos 13 sin otro egreso. |
| Confirmar incidencia sin reembolso | No cerrar financieramente hasta aplicar su dinero. |
| Saldo personal con más precisión que `decimal` | Conservar texto completo; no deserializar como `double`. |
| Ajuste manual de documento automático | Mostrar pendiente no resuelto; no asumir condonación aplicada. |
| Timeout después de un alta | Consultar por IDs/clave conocida; no repetir el dinero. |
| Cierre financiero y movimiento de día siguiente | Conservar fotografía y saldo históricos. |

Comprobaciones del backend y de los ejemplos C# se registran al final de este documento. La conectividad, el framework real, el manejo de tokens y los formularios del escritorio se validan en ese proyecto. No se conectó a producción para generar esta documentación.

## 9. Instrucciones para el chat que desarrolla el escritorio

Implementar esta versión del contrato sobre la arquitectura existente del proyecto C#. Leer primero los cambios, envolturas y limitaciones. Conservar autenticación, recuperación de sesión y módulo individual ya integrados; añadir la capa administrativa y adaptar los procesos existentes afectados. Usar los identificadores reales que devuelve la API y DTOs separados para lectura/escritura.

Priorizar: transporte y errores → catálogos/cuentas → dinero y aplicaciones → cuentas personales → consolidación/matriz/liquidación → auxiliares/gastos/laboral → activos/obras/financiamientos/incidencias → capital/cierre/auditoría. Mantener pendientes las capacidades señaladas en la sección 6. No introducir llamadas a endpoints propuestos que no figuren en este documento ni hacer escrituras directas a la base de datos.

Antes de probar escrituras, verificar con el responsable del backend que el servidor de pruebas tenga las migraciones correspondientes. No ejecutar `migrate:fresh`, reconstrucciones o alteraciones de maestros desde el cliente. Las tablas protegidas siguen siendo `users`, `trabajadores`, `subagencias`, `clientes` y `mesas_cambio`.



<a id="referencia-caja"></a>
## Apéndice A. Contrato individual de caja

Se conservan los números de los apartados 1–15 y 18–19 de la guía individual para facilitar la comparación. Los ejemplos de cliente de sus antiguos apartados 16–17 se sustituyen aquí por la integración de la sección 7; el cliente individual ya instalado puede mantenerse donde cumpla este contrato.

<a id="referencia-caja-1"></a>
### A.1 Alcance y condiciones de integración

El módulo representa la jornada de un cajero mediante un **arqueo**. A ese arqueo se vinculan los movimientos, operaciones de cambio, cheques, conteos de efectivo y registros auxiliares del día. El backend calcula el resumen y guarda el cierre.

La implementación reproduce los registros base del Excel individual de caja «Eveling», incluida la continuidad de apertura de la macro `AirPak1`. También permite registrar los envíos de los dos formatos DHL y los enrolamientos. La consolidación entre trabajadores y los nuevos procesos administrativos se describen en el apéndice B de este mismo documento. La importación automática de archivos sigue pendiente.

Las migraciones y datos existentes de `users`, `trabajadores`, `subagencias`, `clientes` y `mesas_cambio` están protegidos. Los nuevos registros pueden referenciar sus identificadores existentes. Registrar un enrolamiento no crea un cliente, y vincular una operación de caja a una mesa no modifica la mesa.

**Condición de despliegue:** los cambios están preparados en el repositorio; no se han aplicado a la base real durante este trabajo. Si ya se ejecutó una versión anterior de las migraciones de expansión, modificar sus archivos `create` no actualiza esa base: hará falta preparar y aplicar migraciones incrementales según su estado real. Esta guía no confirma que un servidor publicado ya tenga esta versión.

No existe una ruta de versión del contrato. Conviene coordinar la actualización del backend y del escritorio, y conservar esta fecha de revisión con la versión del cliente.

<a id="referencia-caja-2"></a>
### A.2 Dirección base, autenticación y permisos

<a id="referencia-caja-2-1"></a>
#### A.2.1 Transporte

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

<a id="referencia-caja-2-2"></a>
#### A.2.2 Inicio de sesión

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

<a id="referencia-caja-2-3"></a>
#### A.2.3 Alcance de los datos

| Perfil | Lecturas y escrituras del módulo |
| --- | --- |
| Cajero activo | Consulta sus jornadas y registros; captura en sus jornadas abiertas. Consulta las cajas de su agencia, los precios de su agencia/globales y el catálogo de servicios. |
| Administrador activo | Consulta todas las jornadas; puede capturar en representación de un cajero y administrar cajas, servicios y precios. También realiza las vinculaciones supervisadas entre movimientos. |

El rol administrativo reconocido es `Administrador`, ignorando mayúsculas y espacios extremos. La agencia del cajero se obtiene de su trabajador asociado. La jornada conserva su propia agencia histórica.

Los filtros `id_usuario` o `id_arqueo` nunca conceden acceso a registros de terceros. Habitualmente un registro fuera del alcance devuelve 404; determinadas acciones explícitas devuelven 403 o 422. No interpretar un 404 como permiso para crear una copia del registro.

Las rutas existentes de alta, modificación y eliminación de usuarios requieren administrador. Esta guía no redefine los permisos ni los contratos de todos los módulos históricos.

<a id="referencia-caja-3"></a>
### A.3 Tipos de datos y reglas de serialización

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

<a id="referencia-caja-4"></a>
### A.4 Inventario de rutas y claves de respuesta

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

<a id="referencia-caja-4-1"></a>
#### A.4.1 Respuestas comunes

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

<a id="referencia-caja-4-2"></a>
#### A.4.2 Filtros y orden

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

<a id="referencia-caja-4-3"></a>
#### A.4.3 Rutas especializadas y compatibles

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

<a id="referencia-caja-5"></a>
### A.5 Modelo de jornada y campos del arqueo

<a id="referencia-caja-5-1"></a>
#### A.5.1 Campos de apertura

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

<a id="referencia-caja-5-2"></a>
#### A.5.2 Primera apertura

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

<a id="referencia-caja-5-3"></a>
#### A.5.3 Apertura de un día posterior

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

<a id="referencia-caja-5-4"></a>
#### A.5.4 Actualización durante la jornada

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

<a id="referencia-caja-5-5"></a>
#### A.5.5 Campos de respuesta calculados o administrativos

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

<a id="referencia-caja-6"></a>
### A.6 Captura de movimientos financieros

En los recursos de esta sección `id_arqueo` es obligatorio al crear e inmutable. Los importes de movimiento son positivos; la dirección se expresa mediante `tipo`, no mediante un monto negativo. `estado` por defecto es `registrado`; también admite `anulado`. `observacion` admite texto/null hasta 10000 caracteres y `motivo` puede explicar una modificación en auditoría.

`fecha_operacion` es opcional y admite fecha/hora; si se informa, su fecha debe corresponder a la jornada. Omitirla no asigna automáticamente una hora real de operación: los timestamps de creación son otro dato.

<a id="referencia-caja-6-1"></a>
#### A.6.1 Entradas y salidas

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

<a id="referencia-caja-6-2"></a>
#### A.6.2 Pases entre cajas

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

<a id="referencia-caja-6-3"></a>
#### A.6.3 Remesas bancarias

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

<a id="referencia-caja-6-4"></a>
#### A.6.4 Efecto del medio de movimiento

El resumen actual suma o resta el importe completo de movimientos `efectivo`, `cheque`, `mixto` y de aquellos con medio null. Un medio `transferencia` queda fuera del saldo físico combinado de efectivo y cheques. El desglose mixto preserva información, pero no provoca un segundo movimiento ni crea custodias de cheques.

Indicar el medio real cuando se conoce. No usar `transferencia` para ocultar una entrega física, ni generar movimientos adicionales a partir del desglose de uno ya registrado.

<a id="referencia-caja-7"></a>
### A.7 Cheques: operación e inventario documental

<a id="referencia-caja-7-1"></a>
#### A.7.1 Registro de la operación original

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

<a id="referencia-caja-7-2"></a>
#### A.7.2 Custodia de apertura y cierre

`POST /api/custodiascheques`:

```json
{"id_arqueo": 123, "id_cheque": 40, "momento": "cierre"}
```

`id_arqueo`, `id_cheque` y `momento` son obligatorios. `momento` es `apertura` o `cierre`. Opcionales: `observacion`, `moneda`, `monto`; los dos últimos se obtienen del cheque y, si se envían, deben coincidir. El cheque debe estar vigente y tener principal conocido. La combinación arqueo/cheque/momento es única.

La custodia es la lista explícita de documentos presentes; no se deriva de `estado: en_custodia`. Consultar `/custodiascheques?id_arqueo=123&momento=cierre` o `/arqueos/123/registros` para obtener los documentos del cierre. `/cheques/arqueo/123` devuelve las operaciones originadas ese día y puede excluir cheques arrastrados de días anteriores.

En una apertura manual, capturar las custodias de apertura que expliquen el principal inicial declarado. Antes de cerrar, el servidor exige que su suma coincida si el componente inicial de cheques es conocido. Si es null, la API conserva esa incertidumbre y no inventa una composición cero.

En continuidad, las custodias de apertura se copian automáticamente y son inmutables. Al cierre se registran de nuevo los documentos que continúan presentes. Un cajero puede contar un cheque propio de la jornada o uno recibido en la apertura; los demás casos requieren supervisión. No crear otra operación de cheque para arrastrarlo: repetiría la ganancia del origen.

El principal y la moneda de un cheque no pueden cambiar mientras tenga custodias; tampoco puede anularse en ese caso. Los inventarios de jornadas cerradas quedan protegidos.

<a id="referencia-caja-7-3"></a>
#### A.7.3 Seguimiento después del cierre

Un cheque cuyo origen ya está cerrado permite PATCH únicamente con `estado`, `fecha_liquidacion`, `observacion` y `motivo`. No enviar `id_arqueo` ni otros campos, aunque conserven sus valores. No puede anularse ni reactivarse mediante seguimiento. Actualizar el estado documental no retira automáticamente el cheque de las custodias ni registra una salida bancaria; esas declaraciones se realizan explícitamente en la jornada correspondiente.

<a id="referencia-caja-8"></a>
### A.8 Tasas y operaciones de cambio de caja

<a id="referencia-caja-8-1"></a>
#### A.8.1 Precios del día

`POST /api/tasascambioarqueo`:

```json
{"id_arqueo": 123, "tipo": "Compra", "numero_precio": 1, "tasa": "36.500000"}
```

Los cuatro campos son obligatorios. `tipo` es `Compra`/`Venta`; `numero_precio` es un entero de 1 a 4; `tasa` es positiva y admite 6 decimales. Solo puede existir una combinación jornada/tipo/número. En PATCH solo cambia la tasa; jornada, tipo y número son inmutables. No acepta `observacion` como campo propio. DELETE elimina el precio si las operaciones referenciadas lo permiten.

<a id="referencia-caja-8-2"></a>
#### A.8.2 Operación individual

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

<a id="referencia-caja-8-3"></a>
#### A.8.3 Convivencia con las mesas existentes

Los registros históricos de `mesas_cambio` no se incorporan automáticamente al resumen de caja. Para incorporar una mesa existente se crea una operación de caja con `id_mesa_cambio`; el servidor valida propietario, fecha, tipo, estado, importes, tasa y cliente. El enlace es único y no altera la mesa. No generar dos operaciones de caja para el mismo hecho.

El alta histórica de una mesa y el alta de su operación de caja son peticiones diferentes; no existe una transacción HTTP conjunta ni compensación automática entre ambos módulos. El escritorio debe mostrar y reconciliar una creación parcial si falla la segunda petición. Un borrado/anulación en un módulo no sincroniza automáticamente el otro.

<a id="referencia-caja-9"></a>
### A.9 Conteo físico por denominaciones

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

<a id="referencia-caja-10"></a>
### A.10 Resumen y cierre

<a id="referencia-caja-10-1"></a>
#### A.10.1 Resumen actualizado

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

El objeto `Arqueo` del ejemplo está abreviado; en la respuesta se devuelve el modelo con sus columnas. Estos números corresponden al ejemplo continuo: apertura 1000 NIO/100 USD, biller 50 NIO, gasto 10 NIO, demás totales cero, sin otros movimientos y conteos del apartado A.9. Los ejemplos aislados de pases, cheques y remesas no deben añadirse a este escenario si se esperan estos resultados.

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

<a id="referencia-caja-10-2"></a>
#### A.10.2 Cierre definitivo

`POST /api/arqueos/123/cerrar`:

```json
{"confirmar_cheques": true, "motivo": "Conteo físico y documentos de caja verificados"}
```

`confirmar_cheques` es obligatorio y debe aceptarse explícitamente, incluso si el inventario es cero; `motivo` es opcional/null hasta 10000 caracteres. La interfaz debe presentar la lista final de cheques y la confirmación al cajero.

El servidor exige jornada abierta, seis totales informados, ambos conteos contados y coherencia del inventario inicial conocido. Recalcula y guarda finales, esperados, diferencias, principal y ganancias de cheques, autor y timestamps del cierre. La transacción incluye auditoría.

La respuesta es HTTP 200 con `Arqueo` cerrado y `Status: 200`, **sin clave `Resumen`**. No enviar finales ni diferencias en el cierre esperando sobrescribir el cálculo. Una diferencia por sí misma no impide cerrar: el escritorio debe mostrarla antes de confirmar. No hay endpoint de reapertura.

Los registros financieros quedan bloqueados después del cierre, también para administrador. Solo se permite el seguimiento específico de DHL y cheques descrito en esta guía. Anular un arqueo usa DELETE con motivo y solo admite jornadas abiertas; el estado anulado también bloquea nuevas capturas.

<a id="referencia-caja-10-3"></a>
#### A.10.3 Secuencia de pantallas sugerida

1. Iniciar sesión y consultar `/arqueos?estado=abierto`. Recuperar el arqueo existente si lo hay, sin crear otro al abrir una ventana.
2. Si no hay jornada abierta, consultar `/arqueos/ultimo/{id_usuario}` y abrir según primera apertura o continuidad. Un último anulado no debe elegirse manualmente como predecesor.
3. Cargar catálogos y `/arqueos/{id}/registros`. Conservar los IDs devueltos para ediciones.
4. Capturar registros y seis totales. Tras cada escritura confirmada, actualizar la vista o volver a pedir resumen.
5. Cargar/guardar conteos de ambas monedas y custodias de cierre. Revisar pendientes, diferencias y documentos.
6. Solicitar confirmación de cierre al cajero; enviar la acción de cierre una sola vez y volver a leer la jornada.
7. Habilitar consultas del histórico y seguimientos permitidos. La nueva fecha usa una nueva apertura, sin reutilizar el ID anterior.

No ejecutar simultáneamente guardados pendientes y cierre desde botones diferentes. Las escrituras individuales están protegidas por transacciones; no existe una transacción que abarque toda una pantalla o varias peticiones HTTP.

<a id="referencia-caja-11"></a>
### A.11 DHL y sus detalles

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

<a id="referencia-caja-12"></a>
### A.12 Enrolamientos, conteos de servicios y papelería

<a id="referencia-caja-12-1"></a>
#### A.12.1 Enrolamientos por persona

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

<a id="referencia-caja-12-2"></a>
#### A.12.2 Conteos diarios de servicios

`POST /api/conteosservicios`:

```json
{"id_arqueo": 123, "id_servicio": 9, "cantidad": 0, "estado": "registrado"}
```

Los primeros tres campos son obligatorios; `cantidad` es un entero entre 0 y 4294967295. Admite `observacion` y estado `registrado`/`anulado`. La combinación jornada/servicio es única, incluso después de anular; actualizar la fila existente en lugar de crear otra. Si procede reactivarla mientras está abierto, PATCH `estado: registrado` con el contexto de corrección.

Estos conteos no se calculan contando enrolamientos ni operaciones financieras y no producen importes en caja. El servicio se obtiene del catálogo y no de una lista de IDs fijos en el escritorio.

<a id="referencia-caja-12-3"></a>
#### A.12.3 Papelería

`POST /api/conteospapeleria`:

```json
{"id_arqueo": 123, "concepto": "Sobres", "cantidad": 12}
```

Obligatorios: `concepto` (texto hasta 100), `cantidad` (entero 0 a 4294967295). Opcionales/null: `id_arqueo`, `id_subagencia`, `id_servicio`, `fecha` y `observacion` (10000). `id_usuario` es opcional, por defecto el autenticado; otro usuario requiere administrador.

Con arqueo, el servidor toma propietario, agencia y fecha de la jornada. Una fecha/agencia proporcionada debe coincidir. Sin arqueo puede conservarse fecha null para un dato sin periodo conocido; no se transforma automáticamente en un movimiento del día. Propietario y arqueo son inmutables tras la creación. No se interpreta automáticamente como inventario inicial, consumo o saldo, ni se valorizan las cantidades.

<a id="referencia-caja-13"></a>
### A.13 Catálogos administrables

Las lecturas siguen las reglas de alcance del apartado A.2. Las escrituras de estos tres recursos requieren administrador.

| Recurso | Campos POST obligatorios | Opcionales y reglas |
| --- | --- | --- |
| `cajas` | `id_subagencia`, `codigo` (30), `nombre` (100) | `estado`: activo/inactivo, por defecto activo. Código único por agencia. |
| `servicioscaja` | `codigo` (50), `nombre` (100) | `categoria` texto/null (100), `estado`: activo/inactivo. Código único global, inmutable cuando ya fue utilizado. |
| `precioproveedordolar` | `proveedor_actual` (255), `precio_dolar` positivo de hasta 6 decimales | `id_subagencia` null = global; `vigente_desde` fecha/hora, por defecto ahora; `estado` activo/inactivo; `id_usuario` es autor autenticado e inmutable. |

Todos admiten PATCH parcial y DELETE con motivo, que los inactiva conservando referencias. No hay seeds de servicios ni correspondencias automáticas entre nombres de Excel y códigos; configurar el catálogo real antes de usar IDs en el escritorio.

La tasa de referencia del arqueo, los precios diarios compra/venta y la cotización del proveedor son datos separados. Consultar el último proveedor puede ayudar a mostrar una referencia actual, pero no reemplaza la tasa histórica de una operación ni modifica jornadas.

<a id="referencia-caja-14"></a>
### A.14 Extracción para verificación e historial

<a id="referencia-caja-14-1"></a>
#### A.14.1 Todos los registros de una jornada

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

<a id="referencia-caja-14-2"></a>
#### A.14.2 Auditoría

```text
GET /api/historialcaja?id_arqueo=123&per_page=100&page=1
GET /api/historialcaja?entidad=operaciones_cambio_caja&id_registro=55
GET /api/historialcaja/200
```

La lista y la consulta individual comparten el nombre `Historial`; la primera contiene un array y `Paginacion`, la segunda un objeto. Campos del registro: `id`, `id_usuario` (actor), `id_arqueo` nullable, `entidad` (nombre real de tabla), `id_registro`, `accion`, `antes`, `despues`, `motivo`, `ip`, `created_at`, `updated_at`.

Acciones actuales: `abrir`, `arrastrar`, `crear`, `actualizar`, `anular`, `eliminar`, `cerrar`. `antes` puede ser null al crear; `despues` es null al eliminar físicamente. Los snapshots conservan atributos de persistencia y no garantizan los mismos casts de tipos que la respuesta del modelo: tratarlos como JSON dinámico. No hay rutas para escribir o eliminar historial.

<a id="referencia-caja-15"></a>
### A.15 Errores, conflictos y repetición de peticiones

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

El módulo individual no expone `Idempotency-Key`, ETag ni control general de versión para sus escrituras. Los consolidados administrativos sí tienen versiones de corte. No hay captura por lotes. No hay garantía general contra duplicados por repetir un POST: algunas combinaciones son únicas, otras (como guías DHL o enrolamientos) deliberadamente no. Tras un timeout o pérdida de red, el resultado de la escritura es desconocido; consultar los registros antes de reenviar. Evitar reintentos automáticos de escrituras y bloquear el doble clic mientras se procesa una operación.

<a id="referencia-caja-18"></a>
### A.18 Cambios que debe incorporar el escritorio existente

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

<a id="referencia-caja-19"></a>
### A.19 Comprobaciones de aceptación del cliente

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

La validación vigente de esta entrega se indica al final del documento; las verificaciones anteriores del ejemplo individual no sustituyen las del servidor o del escritorio actual.


<a id="referencia-administracion"></a>
## Apéndice B. Contrato administrativo completo

<a id="referencia-administracion-1"></a>
### B.1 Alcance y conexión con caja

La consolidación toma jornadas de una fecha exacta, agrupadas por la agencia que conserva cada arqueo. Calcula contado, utilidad, compras/ventas de USD, WU, Biller y diferencias físicas. La comparación con matriz y la liquidación son procesos separados: una diferencia de servicio no se convierte automáticamente en faltante de un cajero.

Las cuentas bancarias/POS y auxiliares se registran en tablas nuevas. Una **operación financiera** representa el dinero recibido, pagado o trasladado; una **aplicación financiera** explica a qué gasto, cargo, comisión, activo, cuota o incidencia corresponde. Aplicar un pago no mueve el banco por segunda vez.

Los registros de caja ya cerrados pueden vincularse como origen. El enlace conserva su importe, moneda, ID y una copia de sus datos; no escribe sobre la jornada ni sobre la operación original. Los nuevos procesos no modifican `users`, `trabajadores`, `clientes`, `subagencias` ni `mesas_cambio`.

La captura de los datos administrativos y del reporte de matriz es explícita. No hay importador de Excel, búsqueda de libros externos, sincronización con AirPak, carga de archivos ni generación de hojas de cálculo. `documentos` conserva metadatos y SHA-256 de una evidencia que ya existe; no almacena ni descarga su contenido.

<a id="referencia-administracion-2"></a>
### B.2 Autenticación, valores y respuestas

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

<a id="referencia-administracion-3"></a>
### B.3 Consolidación diaria y reporte de matriz

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

<a id="referencia-administracion-4"></a>
### B.4 Liquidación y depósitos en tránsito

POST `liquidaciones` requiere `id_arqueo_madre` cerrado. La primera liquidación de cada alcance necesita `saldo_anterior_usd` explícito, incluso cero, y `motivo_saldo_inicial`. Las siguientes heredan **el negativo del pendiente** del cierre anterior del mismo alcance. No se permite saltarse un borrador anterior ni reemplazar ese arrastre.

```text
proyección_USD = proyección_NIO / TC − saldo_anterior_USD − anticipo_USD − comisión_anticipo_USD
pendiente_USD = proyección_USD + depósitos_acreditados_USD
```

`anticipo_usd` y `comision_anticipo_usd` son datos declarados, por defecto cero. Para comparar el saldo comunicado se deben enviar juntos `saldo_matriz_nio`, `saldo_interno_nio` y `corte_comparacion_at` de esa fecha. La diferencia de esos saldos no sustituye la conciliación de cuatro totales.

Un depósito utiliza un `egreso` financiero real, sin aplicaciones previas. POST `depositosliquidacion` lleva liquidación, operación y fecha. Mientras esté pendiente exige `id_cuenta_transito`: cuenta **por cobrar**, de la misma moneda y con `rubro_capital: pendiente`. Se genera un cargo por el dinero aún no acreditado. Si se informa `fecha_acreditacion` al crearlo, debe estar dentro del corte.

POST `depositosliquidacion/{id}/acreditar` recibe `{"fecha_acreditacion":"2026-11-06"}`. Genera el abono y su aplicación contra el cargo de tránsito; no genera otro egreso bancario. Puede ocurrir después del cierre de su liquidación, con fecha de efecto de un periodo financiero abierto. El saldo de tránsito del corte anterior se conserva, y la acreditación se reconoce una vez en la siguiente liquidación del mismo alcance. La conversión del depósito a USD utiliza la tasa del corte donde se reconoce.

POST `liquidaciones/{id}/cerrar` guarda `resultado` y devuelve `Resultado.Registro` y `Resultado.Resumen`. Los resúmenes posteriores de esa liquidación devuelven la fotografía cerrada; para el estado actual consultar también los depósitos y el siguiente corte. No se permite anular un depósito de una liquidación cerrada.

<a id="referencia-administracion-5"></a>
### B.5 Cuentas, operaciones y aplicaciones

`contrapartes` separa proveedores, propietarios, colaboradores, bancos y otras personas del catálogo protegido de clientes. Una cuenta tiene naturaleza, moneda, apertura, saldo inicial y clasificación explícita para capital. La clasificación por defecto es `excluido`.

| Tipo de cuenta | Rubros permitidos | Efecto de saldo en capital |
| --- | --- | --- |
| banco / pos | banco, excluido | Se agrega en su moneda si está en banco. |
| por_cobrar / recargas | pendiente, excluido | Se agrega al pendiente neto. |
| por_pagar | pendiente, deuda, excluido | Se resta como pendiente o como deuda. |
| fondo | pendiente, excluido | Se resta como fondos de terceros; un saldo negativo representa saldo a favor del negocio. |
| liquidacion | excluido | La liquidación se toma de su módulo, evitando duplicarla. |

El saldo es `inicial + cargos − abonos` a la fecha solicitada. Desactivar una cuenta no borra su saldo histórico del reporte. Un saldo inicial agregado no constituye un cargo individual al cual aplicar un cobro: para cobrar partidas heredadas, capturarlas como cargos identificados, sin duplicarlas también en el inicial.

Los ajustes auxiliares manuales se crean en `movimientoscuenta` con `motivo`, `clave`, cargo/abono, importe, cuenta y fecha. No admiten banco/POS ni la asignación manual de `id_operacion`. `aplicacionescuenta` cruza un abono manual con un cargo manual o automático de la misma cuenta y dentro de sus pendientes; no mueve dinero. Los abonos automáticos se administran desde su origen. Para pagar un cargo manual mediante banco/caja, utilizar `aplicaciones` con `id_cargo`; los documentos automáticos mantienen sus destinos específicos.

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

<a id="referencia-administracion-6"></a>
### B.6 Gastos, obligaciones laborales y comisiones

`gastos` registra fecha, periodo, ámbito administrativo/operativo/personal, categoría, concepto, importe y moneda; permite contraparte, agencia y centro de costo. Reconocer el gasto no implica que ya se haya pagado. Su pago se vincula desde `aplicaciones`. Si el periodo de un gasto cruza el intervalo del informe de capital, aparece como pendiente de distribución; el sistema no inventa un prorrateo.

Si el gasto queda por pagar, informar `id_cuenta` de una CxP de la misma moneda: se genera su cargo y `id_movimiento_origen`. Aplicar el pago a `id_gasto` reduce automáticamente esa deuda y permite pagos parciales; no crear otro cargo manual por el mismo gasto. La cuenta es opcional para capturas que no requieren auxiliar individual.

`obligacioneslaborales` conserva trabajador, periodo, concepto, importe, moneda y cuenta auxiliar. Un anticipo usa CxC; salario, prestaciones y otras obligaciones usan CxP. Salario, aguinaldo, vacaciones, indemnización, INSS patronal e INATEC generan una clasificación automática de gasto. El anticipo y el INSS laboral no generan ese gasto adicional. `descuento` no se captura como obligación: se registra mediante compensación. No hay cálculo legal de nómina ni tasas legales precargadas.

Al entregar un anticipo, registrar su egreso bancario o reconocer su salida de caja y vincularlo mediante `id_operacion_anticipo`. Debe ser el importe completo, de la misma moneda, sin otra clasificación; queda reservado para ese anticipo. El enlace es opcional para admitir saldos heredados identificados documentalmente. Recuperar un anticipo en dinero utiliza una aplicación de ingreso contra `id_obligacion`.

POST `compensacioneslaborales` cruza `id_anticipo` e `id_obligacion` de un mismo trabajador y moneda, por fecha e importe. Disminuye CxC del anticipo y CxP de la obligación **sin nuevo movimiento de dinero**. El salario restante se paga con un egreso y una aplicación; no volver a clasificar como gasto el anticipo ni la compensación.

Para descontar INSS laboral de un salario bruto, usar `id_retencion` en lugar de `id_anticipo`, junto con `id_obligacion` del salario. La retención debe ser una obligación `inss_laboral` del mismo trabajador y moneda. Esta compensación disminuye solo el salario pendiente: conserva la obligación de INSS para su pago posterior al destinatario. Así, salario bruto 500 y retención 50 dejan salario por pagar 450 e INSS por pagar 50, con gasto salarial 500; no dos gastos ni una deuda salarial adicional. Se permiten descuentos parciales sin superar el importe de la retención. La tasa y el importe de la retención son datos declarados.

`comisiones` distingue proveedor/operador/bancaria y naturaleza cobrar/pagar, periodo, servicio y beneficiario. Genera un cargo en CxC o CxP de su moneda. El cobro o pago utiliza una operación y una aplicación. El indicador global descuenta solo las comisiones de proveedor por cobrar que hayan sido efectivamente aplicadas como cobradas dentro del periodo, no toda comisión devengada ni todo ingreso sin aplicar.

<a id="referencia-administracion-7"></a>
### B.7 Obras, activos y financiamientos

`proyectos` identifica una obra. Sus `movimientosobra` separan costo, anticipo, recuperación y devolución. Admiten importe o cantidad/precio; si se informa cantidad o precio, deben enviarse ambos. El importe explícito se conserva; si falta, se deriva del producto. No se usa un alquiler recuperado para modificar silenciosamente el costo bruto.

`aplicacionesobra` cruza un anticipo **ya pagado** con un costo certificado, del mismo proyecto y moneda, sin otro egreso. El reporte utiliza `costo + anticipo − anticipo_aplicado − recuperación − devolución`. Los pagos de costos, anticipos y recuperaciones se documentan mediante `aplicaciones`; el registro de obra conserva el reconocimiento aunque aún no esté pagado.

`activos` conserva costo unitario, cantidad y moneda de adquisición; `monto` se calcula en el servidor. `eventosactivos` registra traslados o bajas con cantidades por ubicación, en orden cronológico. La consulta `inventario` muestra las cantidades al corte; el indicador global utiliza solo cantidades vigentes por su costo registrado. No hay depreciación automática, reclasificación automática de obra a edificio, ni cambio automático de costo histórico. Si se quiere conservar una valoración histórica ya fijada en NIO, registrar ese costo en NIO con su evidencia.

`financiamientos` requiere una cuenta CxP nueva de la misma moneda, inicial cero y sin movimientos para no duplicar el principal. Registra contrato, principal, tasa informativa/base, periodicidad y vencimiento. Puede vincular `id_operacion_desembolso` a un ingreso real por el principal completo, reservado para ese contrato; la deuda también puede ser heredada, sin ese enlace.

`cuotas` recibe el calendario pactado y sus componentes capital/interés/seguro/comisión. El servidor calcula el total y evita programar más principal que el registrado. La tasa del contrato no genera un calendario automáticamente. No se presupone sistema francés, interés simple ni otra fórmula de amortización.

Una aplicación a `id_cuota` reduce la deuda **solo por el capital**. Interés/seguro/comisión generan un gasto financiero automático en la fecha del pago. Un pago parcial exige `componentes` con los cuatro importes, cuya suma equivale a `monto_destino`; un pago por el saldo completo puede omitirlos. Se puede abonar directamente principal mediante `id_financiamiento`; no se reprograman automáticamente las cuotas tras ese abono.

<a id="referencia-administracion-8"></a>
### B.8 Incidencias y recuperación de servicios

`incidencias` conserva servicio, importe/moneda, causa, Biller, referencia del cliente, caso/ticket y documento. Puede vincular jornada, operador y envío DHL. Si hay jornada, el operador y la agencia corresponden a esa jornada; si hay envío, debe corresponder al servicio DHL y a la misma jornada. Una cuenta CxC/CxP opcional genera el cargo correspondiente.

`gestionesincidencia` registra eventos de solicitud, confirmación, apertura o cierre y actualiza el estado de gestión del caso. Confirmar una anulación con el proveedor **no registra un reembolso**. Para registrar el dinero recuperado/pagado, crear la operación y aplicarla a `id_incidencia`. El caso solo admite gestión de cierre con pendiente financiero cero. Para retirar una aplicación de reembolso de un caso cerrado, registrar primero una gestión de reapertura, en periodo abierto.

<a id="referencia-administracion-9"></a>
### B.9 Indicadores globales y cierre financiero

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

<a id="referencia-administracion-10"></a>
### B.10 Acciones y consultas adicionales

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
| GET `capital` | Parámetros de consulta del apartado B.9. | Componentes, fuentes e indicadores. |

La consulta de financiamiento muestra el calendario completo; no interpreta todas sus cuotas como vencidas a la fecha de saldo. La ficha de incidencia contiene el estado de gestión actual y el historial filtrado, mientras que el pendiente se calcula al corte solicitado.

<a id="referencia-administracion-11"></a>
### B.11 Consumo desde C#

El inicio de sesión y el token son los de [la guía del cliente](#referencia-caja). Este módulo utiliza claves uniformes distintas de las claves particulares de algunos endpoints antiguos de caja. Usar `decimal`, cultura invariante y lectura de números JSON entre comillas; no convertir importes a `double`. No recalcular saldos ni escribir campos derivados en el cliente.

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

<a id="referencia-administracion-12"></a>
### B.12 Implementación, esquema y validación

Las rutas están en [routes/administracion.php](../routes/administracion.php), cargadas dentro del grupo Sanctum existente. [AdministracionController](../app/Http/Controllers/Api/AdministracionController.php) proporciona el contrato común; los servicios de [administración](../app/Services/Administracion) separan consolidación, matriz, cuentas, aplicaciones y capital. [Recursos.php](../app/Support/Administracion/Recursos.php) define las capturas admitidas y sus validaciones. Los modelos están en [Models/Administracion](../app/Models/Administracion).

Se completaron las dos migraciones vacías de `arqueo_madre` y `detalle_arqueo_madre`, y se agregaron las migraciones administrativas `2026_11_05_000000`, `000001`, `000002` y `000003` (movimientos personales). Las migraciones protegidas no se editaron. **No se ejecutaron migraciones en una base real.** Las definiciones modificadas corresponden a tablas que el usuario indicó sin datos en producción; si otra instalación ya ejecutó un esquema anterior, se requiere adaptar ese esquema mediante migraciones incrementales antes de desplegar.

```powershell
php tests/Schema/caja_migrations.php
php tests/Schema/administracion_api.php
```

La segunda prueba incluye la regresión de caja. Ambas usan SQLite en memoria y datos ficticios; la primera también compila DDL MySQL con una conexión bloqueada, sin servidor. Verifican creación/reversión, claves foráneas, protección de maestros, permisos, cálculos, aplicaciones parciales, compensaciones, depósitos posteriores y cierres. No sustituyen una validación en MySQL aislado ni una prueba del login/Sanctum o del cliente de escritorio.

Quedan como integraciones posteriores el importador de los libros, el detalle transaccional del reporte externo, la conciliación bancaria contra extractos y la exportación de reportes. No se inventaron reglas para convertir automáticamente sobrantes/faltantes mixtos a una deuda NIO, calcular nómina legal, depreciar activos ni amortizar préstamos. Los movimientos y conceptos identificados sí pueden capturarse con moneda, fecha, naturaleza y evidencia explícitas.

<a id="referencia-administracion-13"></a>
### B.13 Referencia de recursos y campos de captura

Para cada recurso se admiten GET listado/detalle y POST alta, excepto `historial` (solo lectura). PATCH/PUT y DELETE tienen las restricciones de estado y dependencias descritas; `cierres` solo admite alta y lectura. `gestionesincidencia` y `eventosactivos` se conservan como eventos y no admiten cambios financieros ni anulación. El detalle del consolidado es interno y se consulta mediante su resumen.

En las tablas siguientes, «requerido» se refiere a POST; en PATCH/PUT se omiten los campos que no cambian. «Opcional» permite omisión; solo se acepta `null` si se indica. Los campos calculados/internos no forman parte del cuerpo de captura. `motivo` se acepta para auditoría en las capturas y es obligatorio en DELETE, ajustes manuales y cierres según lo descrito. Los IDs y timestamps de respuesta no deben reenviarse como cuerpo completo.


#### B.13.movimientospersonales

Modelo `MovimientoPersonal`; tabla `movimientos_personales`. Ver [flujo, desgloses y ejemplos](#referencia-personales).

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

#### B.13.arqueosmadre

Modelo `ArqueoMadre`; tabla `arqueo_madre`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `tasa_referencia` | Requerido | Decimal, hasta 6 decimales; positivo. |
| `usuarios_esperados` | Requerido | Array no vacío de IDs de usuarios distintos. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

#### B.13.contrapartes

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

#### B.13.documentos

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

#### B.13.cuentas

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

#### B.13.operaciones

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

#### B.13.movimientoscuenta

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

#### B.13.aplicacionescuenta

Modelo `AplicacionCuenta`; tabla `aplicaciones_cuenta_financiera`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_cargo` | Requerido | ID de `movimientos_cuenta_financiera`. |
| `id_abono` | Requerido | ID de `movimientos_cuenta_financiera`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

#### B.13.reportesmatriz

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

#### B.13.detallesreporte

Modelo `DetalleReporteMatriz`; tabla `detalles_reporte_matriz`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_reporte` | Requerido | ID de `reportes_matriz`. |
| `servicio` | Requerido | `wu`, `biller`. |
| `moneda` | Requerido | `NIO`, `USD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

#### B.13.conciliaciones

Modelo `ConciliacionMatriz`; tabla `conciliaciones_matriz`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_arqueo_madre` | Requerido | ID de `arqueo_madre`. |
| `id_reporte` | Requerido | ID de `reportes_matriz`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

#### B.13.liquidaciones

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

#### B.13.depositosliquidacion

Modelo `DepositoLiquidacion`; tabla `depositos_liquidacion`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_liquidacion` | Requerido | ID de `liquidaciones_matriz`. |
| `id_operacion` | Requerido | ID de `operaciones_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `fecha_acreditacion` | Opcional, admite null | Fecha `YYYY-MM-DD`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `id_cuenta_transito` | Opcional, admite null | ID de `cuentas_financieras`. |

#### B.13.centroscosto

Modelo `CentroCosto`; tabla `centros_costo`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `codigo` | Requerido | Texto; máximo 60 caracteres. |
| `nombre` | Requerido | Texto; máximo 255 caracteres. |
| `id_subagencia` | Opcional, admite null | ID de `subagencias`. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |
| `estado` | Opcional | `activo`, `inactivo`; por defecto `activo`. |

#### B.13.gastos

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

#### B.13.obligacioneslaborales

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

#### B.13.comisiones

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

#### B.13.proyectos

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

#### B.13.movimientosobra

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

#### B.13.aplicacionesobra

Modelo `AplicacionAnticipoObra`; tabla `aplicaciones_anticipo_obra`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_anticipo` | Requerido | ID de `movimientos_obra`. |
| `id_costo` | Requerido | ID de `movimientos_obra`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

#### B.13.activos

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

#### B.13.eventosactivos

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

#### B.13.financiamientos

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

#### B.13.cuotas

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

#### B.13.incidencias

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

#### B.13.gestionesincidencia

Modelo `GestionIncidencia`; tabla `gestiones_incidencia`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_incidencia` | Requerido | ID de `incidencias_financieras`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `gestion` | Requerido | `abierto`, `solicitado`, `confirmado`, `cerrado`. |
| `detalle` | Requerido | Texto; máximo 10000 caracteres. |
| `id_documento` | Opcional, admite null | ID de `documentos_financieros`. |

#### B.13.aplicaciones

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

#### B.13.cierres

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

#### B.13.compensacioneslaborales

Modelo `CompensacionLaboral`; tabla `compensaciones_laborales`.

| Campo | Captura POST | Tipo / restricción |
| --- | --- | --- |
| `id_anticipo` | Opcional, admite null | ID de `obligaciones_laborales`. |
| `id_retencion` | Opcional, admite null | ID de obligacion de INSS laboral; excluyente con id_anticipo. |
| `id_obligacion` | Requerido | ID de `obligaciones_laborales`. |
| `fecha` | Requerido | Fecha `YYYY-MM-DD`. |
| `monto` | Requerido | Decimal, hasta 8 decimales; positivo. |
| `observacion` | Opcional, admite null | Texto; máximo 10000 caracteres. |

#### B.13.historial

Modelo `HistorialFinanciero`; tabla `historial_financiero`.

Solo lectura. Devuelve entidad, id_registro, acción, antes/después, motivo, IP, autor y timestamps. Puede filtrarse por entidad e id_registro.


<a id="referencia-personales"></a>
## Apéndice C. Contrato de cuentas personales

<a id="referencia-personales-1"></a>
### C.1 Cuenta, dinero y movimiento personal

La cuenta financiera debe tener `id_contraparte` vigente. Depósitos y retiros utilizan `tipo: fondo`; los préstamos entregados, `tipo: por_cobrar`. Crear la cuenta y su titular con las rutas administrativas existentes. Una misma contraparte puede tener varios fondos o CxC, separados por contrato y moneda.

El dinero se representa mediante una `operacion` financiera existente:

- Depósito del titular: operación `ingreso`.
- Retiro del titular: operación `egreso`.
- Préstamo que entregamos a una persona: operación `egreso`.

La operación puede provenir de banco/POS o reconocer una entrada/salida ya registrada en una jornada cerrada de caja, siguiendo las reglas del módulo administrativo. Un traslado entre fondos (`transferencia`) no sirve como origen de estos movimientos.

Primero se registra/reconoce la operación financiera y se conserva su ID. Después se registra el movimiento personal contra ella. Son dos peticiones: la operación bancaria ya mueve su cuenta; el movimiento personal actualiza únicamente el auxiliar del particular. Si falla esta segunda captura, se debe consultar/reutilizar la operación original, no volver a registrar el ingreso/egreso.

El alta personal y su movimiento auxiliar se guardan en una misma transacción. Su importe ocupa parte del dinero disponible de la operación, compartiendo el límite con las aplicaciones financieras existentes. Se pueden distribuir importes de una operación entre varios destinos, pero nunca superar el total. Una operación reservada para depósito de matriz, anticipo laboral o desembolso de financiamiento no puede reutilizarse.

<a id="referencia-personales-2"></a>
### C.2 Rutas y captura

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

<a id="referencia-personales-3"></a>
### C.3 Depósito y reintegro del titular

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

<a id="referencia-personales-4"></a>
### C.4 Retiro y deuda del titular

El cuerpo es igual, con `tipo: retiro` y una operación de egreso. El movimiento genera un **abono** en el fondo. Consume primero el saldo positivo; el importe que lo exceda se convierte en deuda del titular, expresada como saldo negativo de esa misma cuenta.

| Saldo anterior | Retiro | Fondos utilizados | Deuda generada | Saldo posterior |
| --- | --- | --- | --- | --- |
| 100 | 40 | 40 | 0 | 60 |
| 100 | 150 | 100 | 50 | −50 |
| −50 | 20 | 0 | 20 | −70 |

Este proceso permite el excedente solicitado: no impone un límite de crédito adicional. El permiso de administrador y la evidencia del egreso identifican la operación autorizada.

<a id="referencia-personales-5"></a>
### C.5 Préstamo entregado y cobro posterior

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

La excepción de aplicar a un cargo automático queda limitada al cargo de un préstamo personal vigente. No habilita aplicaciones manuales a cargos automáticos de gastos, nómina u otros módulos.

<a id="referencia-personales-6"></a>
### C.6 Resultados, saldo y capital

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

<a id="referencia-personales-7"></a>
### C.7 Correcciones y cierres

Los importes, tipo, cuenta, operación, fecha y tasa no se editan después de contabilizar. La corrección consiste en anular y reclasificar en periodo abierto con otra clave. La anulación revierte el movimiento auxiliar y libera el importe de la operación, pero **no devuelve dinero ni anula el movimiento bancario o de caja**. Una devolución efectiva requiere su operación real.

No se puede anular el préstamo mientras tenga cobros/aplicaciones activos. Tampoco se puede retirar o modificar una entrada anterior si alteraría el saldo anterior guardado por un movimiento personal posterior. Los movimientos personales se registran en orden cronológico de su cuenta; los del mismo día se ordenan por su registro. Para corregir antecedentes en un periodo abierto, resolver primero los movimientos personales posteriores.

Los cierres financieros mantienen su protección: no se anulan ni registran efectos en una fecha ya cerrada. Un movimiento del día siguiente no cambia los saldos ni las fotografías de cierres anteriores.

<a id="referencia-personales-8"></a>
### C.8 Esquema y comprobaciones

Se agrega [la migración de movimientos personales](migrations/2026_11_05_000003_movimientos_personales.php), [MovimientoPersonal](../app/Models/Administracion/MovimientoPersonal.php) y [CuentaPersonalService](../app/Services/Administracion/CuentaPersonalService.php). El controlador administrativo común y sus rutas dinámicas exponen el nuevo recurso; los límites compartidos y guardas de origen se integran en los servicios existentes.

```powershell
php tests/Schema/caja_migrations.php
php tests/Schema/cuentas_personales_api.php
```

La segunda prueba incluye las regresiones de caja y administración. Comprueba depósitos, retiros excedidos, reintegros, apertura positiva/negativa de fondos, préstamos y cobros parciales, moneda distinta, límites compartidos de dinero, vínculos de caja, anulaciones, permisos, cronología e integración con capital/cierre. Se utiliza SQLite en memoria y el esquema MySQL se compila sin conectar a un servidor.

No se modifican los maestros ni las migraciones protegidas, y no se aplicaron migraciones en una base real. Esta entrega completa los tres procesos solicitados; los ajustes de reducción de obligaciones automáticas y la compensación general entre cuentas del informe anterior siguen siendo trabajos distintos.


<a id="catalogo-respuestas"></a>
## Apéndice D. Campos y tipos de respuesta de los 30 recursos administrativos

Este catálogo se extrajo de los modelos, validaciones y columnas del esquema actual. Las capturas y sus reglas completas están en B.13; esta sección añade los campos que devuelve cada modelo, incluidos resultados del servidor. No implica que todos sean editables ni que todas las relaciones se devuelvan anidadas. Las acciones pueden contener además las estructuras descritas en la sección 5.

Todos los modelos incluyen también `id` (entero), `created_at` y `updated_at` (timestamp, admiten null en el esquema). Las columnas nullable se marcan por su esquema, aunque determinadas altas las completen siempre antes de responder. Los campos JSON se mantienen como `JsonElement` hasta tipar su estructura específica.

### D.movimientospersonales

Modelo `MovimientoPersonal`; tabla `movimientos_personales`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `clave` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_operacion` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda_origen` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `tasa_cambio` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `monto_cuenta` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `saldo_anterior` | Cadena decimal / `string` (hasta 32 dígitos de precisión SQL) | No | Servidor/interno; no reenviar en POST/PATCH. |
| `saldo_posterior` | Cadena decimal / `string` (hasta 32 dígitos de precisión SQL) | No | Servidor/interno; no reenviar en POST/PATCH. |
| `fondos_utilizados` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `fondos_incrementados` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `deuda_generada` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `deuda_cancelada` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_movimiento_generado` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.arqueosmadre

Modelo `ArqueoMadre`; tabla `arqueo_madre`.

Estados almacenados: `borrador`, `cerrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `alcance` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `version` | Entero / `long` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `tasa_referencia` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `usuarios_esperados` | Objeto o array JSON / `JsonElement` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `totales` | Objeto o array JSON / `JsonElement` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `cerrado_at` | Cadena de fecha/hora / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.contrapartes

Modelo `ContraparteFinanciera`; tabla `contrapartes_financieras`.

Estados almacenados: `activo`, `inactivo`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `codigo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `nombre` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cliente` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_trabajador` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.documentos

Modelo `DocumentoFinanciero`; tabla `documentos_financieros`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `nombre` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `referencia` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `ubicacion` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `sha256` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha_documento` | Cadena de fecha / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.cuentas

Modelo `CuentaFinanciera`; tabla `cuentas_financieras`.

Estados almacenados: `activo`, `inactivo`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `codigo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `nombre` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `rubro_capital` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha_apertura` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `saldo_inicial` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.operaciones

Modelo `OperacionFinanciera`; tabla `operaciones_financieras`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `clave` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta_origen` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta_destino` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `origen_tipo` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_origen` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_entrada_salida` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_pase` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_remesa` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_operacion_cambio` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `lado` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `origen_snapshot` | Objeto o array JSON / `JsonElement` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |
| `clave_origen_activa` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |

### D.movimientoscuenta

Modelo `MovimientoCuenta`; tabla `movimientos_cuenta_financiera`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_cuenta` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_operacion` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `origen_automatico` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `clave` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.aplicacionescuenta

Modelo `AplicacionCuenta`; tabla `aplicaciones_cuenta_financiera`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_cargo` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_abono` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.reportesmatriz

Modelo `ReporteMatriz`; tabla `reportes_matriz`.

Estados almacenados: `borrador`, `confirmado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `corte_at` | Cadena de fecha/hora / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `proveedor` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `referencia` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.detallesreporte

Modelo `DetalleReporteMatriz`; tabla `detalles_reporte_matriz`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_reporte` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `servicio` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.conciliaciones

Modelo `ConciliacionMatriz`; tabla `conciliaciones_matriz`.

Estados almacenados: `conciliado`, `con_diferencias`, `revisado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_arqueo_madre` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_reporte` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `resultado` | Objeto o array JSON / `JsonElement` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `motivo_revision` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `revisado_at` | Cadena de fecha/hora / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.liquidaciones

Modelo `LiquidacionMatriz`; tabla `liquidaciones_matriz`.

Estados almacenados: `borrador`, `cerrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_arqueo_madre` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_anterior` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `saldo_anterior_usd` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `motivo_saldo_inicial` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `anticipo_usd` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `comision_anticipo_usd` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `saldo_matriz_nio` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `saldo_interno_nio` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `corte_comparacion_at` | Cadena de fecha/hora / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `resultado` | Objeto o array JSON / `JsonElement` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.depositosliquidacion

Modelo `DepositoLiquidacion`; tabla `depositos_liquidacion`.

Estados almacenados: `pendiente`, `acreditado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_liquidacion` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_operacion` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha_acreditacion` | Cadena de fecha / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |
| `id_cuenta_transito` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_transito` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_movimiento_acreditacion` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `clave_operacion_activa` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |

### D.centroscosto

Modelo `CentroCosto`; tabla `centros_costo`.

Estados almacenados: `activo`, `inactivo`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `codigo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `nombre` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.gastos

Modelo `GastoAdministrativo`; tabla `gastos_administrativos`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_desde` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_hasta` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_centro_costo` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `ambito` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `categoria` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_origen` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |
| `origen_automatico` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |

### D.obligacioneslaborales

Modelo `ObligacionLaboral`; tabla `obligaciones_laborales`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_desde` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_hasta` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_trabajador` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_operacion_anticipo` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_origen` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.comisiones

Modelo `ComisionAdministrativa`; tabla `comisiones_administrativas`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_desde` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_hasta` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `naturaleza` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_servicio` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_trabajador` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_origen` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.proyectos

Modelo `ProyectoObra`; tabla `proyectos_obra`.

Estados almacenados: `activo`, `inactivo`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `codigo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `nombre` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.movimientosobra

Modelo `MovimientoObra`; tabla `movimientos_obra`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_proyecto` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `partida` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `cantidad` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `precio_unitario` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.aplicacionesobra

Modelo `AplicacionAnticipoObra`; tabla `aplicaciones_anticipo_obra`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_anticipo` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_costo` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.activos

Modelo `ActivoAdministrativo`; tabla `activos_administrativos`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `codigo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `nombre` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `categoria` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `cantidad` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `precio_unitario` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.eventosactivos

Modelo `EventoActivo`; tabla `eventos_activo`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_activo` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tipo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `cantidad` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia_origen` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia_destino` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `concepto` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.financiamientos

Modelo `Financiamiento`; tabla `financiamientos`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `referencia` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_contraparte` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `principal` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tasa_interes` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `base_tasa` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `periodicidad` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `numero_cuotas` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha_vencimiento` | Cadena de fecha / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_operacion_desembolso` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_origen` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.cuotas

Modelo `CuotaFinanciamiento`; tabla `cuotas_financiamiento`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_financiamiento` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `numero` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `capital` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `interes` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `seguro` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `comision` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `moneda` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.incidencias

Modelo `IncidenciaFinanciera`; tabla `incidencias_financieras`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `referencia` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `servicio` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_arqueo` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_envio_dhl` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_subagencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_operador` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `biller` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `referencia_cliente` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `ticket` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `caso` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `motivo_incidencia` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `moneda` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuenta` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_origen` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `gestion` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.gestionesincidencia

Modelo `GestionIncidencia`; tabla `gestiones_incidencia`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_incidencia` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `gestion` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `detalle` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_documento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.aplicaciones

Modelo `AplicacionFinanciera`; tabla `aplicaciones_financieras`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_operacion` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `tasa_cambio` | Cadena decimal / `decimal` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `monto_destino` | Cadena decimal / `decimal` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_gasto` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_obligacion` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_comision` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_obra` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_activo` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_cuota` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_financiamiento` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_incidencia` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `componentes` | Objeto o array JSON / `JsonElement` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_movimiento_generado` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |
| `id_cargo` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_aplicacion_cuenta_generada` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |

### D.cierres

Modelo `CierreFinanciero`; tabla `cierres_financieros`.

Estados almacenados: `cerrado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `periodo_desde` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_arqueo_madre` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_liquidacion` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `cuentas_capital_trabajo` | Objeto o array JSON / `JsonElement` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `datos` | Objeto o array JSON / `JsonElement` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `huella` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `cerrado_at` | Cadena de fecha/hora / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `motivo` | Cadena / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.compensacioneslaborales

Modelo `CompensacionLaboral`; tabla `compensaciones_laborales`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `id_retencion` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_anticipo` | Entero / `long` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `id_obligacion` | Entero / `long` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `fecha` | Cadena de fecha / `string` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `monto` | Cadena decimal / `decimal` | No | Campo de captura; ver B.13 y reglas de actualización. |
| `id_abono_anticipo` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `id_abono_obligacion` | Entero / `long` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `observacion` | Cadena / `string` | Sí | Campo de captura; ver B.13 y reglas de actualización. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

### D.historial

Modelo `HistorialFinanciero`; tabla `historial_financiero`.

Estados almacenados: `registrado`, `anulado`. Su existencia no autoriza escribirlos directamente; se aplican las acciones y restricciones del recurso.

| Campo JSON | Tipo JSON / C# sugerido | Null | Captura o procedencia |
| --- | --- | --- | --- |
| `entidad` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_registro` | Entero / `long` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `accion` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `antes` | Objeto o array JSON / `JsonElement` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `despues` | Objeto o array JSON / `JsonElement` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `motivo` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `ip` | Cadena / `string` | Sí | Servidor/interno; no reenviar en POST/PATCH. |
| `estado` | Cadena / `string` | No | Servidor/interno; no reenviar en POST/PATCH. |
| `id_usuario_registro` | Entero / `long` | No | Autor determinado por la sesión. |

## Apéndice E. Inventario HTTP administrativo verificado

Las siguientes rutas se extrajeron del router cargado por las pruebas. Se omite HEAD de las filas GET. Tener una ruta PATCH/DELETE no significa que un registro cerrado, automático o con dependencias admita esa acción.

| Método | Ruta exacta |
| --- | --- |
| GET | `/api/administracion/capital` |
| GET | `/api/administracion/movimientospersonales` |
| GET | `/api/administracion/movimientospersonales/{id}` |
| POST | `/api/administracion/movimientospersonales` |
| PUT, PATCH | `/api/administracion/movimientospersonales/{id}` |
| DELETE | `/api/administracion/movimientospersonales/{id}` |
| GET | `/api/administracion/arqueosmadre` |
| GET | `/api/administracion/arqueosmadre/{id}` |
| POST | `/api/administracion/arqueosmadre` |
| PUT, PATCH | `/api/administracion/arqueosmadre/{id}` |
| DELETE | `/api/administracion/arqueosmadre/{id}` |
| GET | `/api/administracion/contrapartes` |
| GET | `/api/administracion/contrapartes/{id}` |
| POST | `/api/administracion/contrapartes` |
| PUT, PATCH | `/api/administracion/contrapartes/{id}` |
| DELETE | `/api/administracion/contrapartes/{id}` |
| GET | `/api/administracion/documentos` |
| GET | `/api/administracion/documentos/{id}` |
| POST | `/api/administracion/documentos` |
| PUT, PATCH | `/api/administracion/documentos/{id}` |
| DELETE | `/api/administracion/documentos/{id}` |
| GET | `/api/administracion/cuentas` |
| GET | `/api/administracion/cuentas/{id}` |
| POST | `/api/administracion/cuentas` |
| PUT, PATCH | `/api/administracion/cuentas/{id}` |
| DELETE | `/api/administracion/cuentas/{id}` |
| GET | `/api/administracion/operaciones` |
| GET | `/api/administracion/operaciones/{id}` |
| POST | `/api/administracion/operaciones` |
| PUT, PATCH | `/api/administracion/operaciones/{id}` |
| DELETE | `/api/administracion/operaciones/{id}` |
| GET | `/api/administracion/movimientoscuenta` |
| GET | `/api/administracion/movimientoscuenta/{id}` |
| POST | `/api/administracion/movimientoscuenta` |
| PUT, PATCH | `/api/administracion/movimientoscuenta/{id}` |
| DELETE | `/api/administracion/movimientoscuenta/{id}` |
| GET | `/api/administracion/aplicacionescuenta` |
| GET | `/api/administracion/aplicacionescuenta/{id}` |
| POST | `/api/administracion/aplicacionescuenta` |
| PUT, PATCH | `/api/administracion/aplicacionescuenta/{id}` |
| DELETE | `/api/administracion/aplicacionescuenta/{id}` |
| GET | `/api/administracion/reportesmatriz` |
| GET | `/api/administracion/reportesmatriz/{id}` |
| POST | `/api/administracion/reportesmatriz` |
| PUT, PATCH | `/api/administracion/reportesmatriz/{id}` |
| DELETE | `/api/administracion/reportesmatriz/{id}` |
| GET | `/api/administracion/detallesreporte` |
| GET | `/api/administracion/detallesreporte/{id}` |
| POST | `/api/administracion/detallesreporte` |
| PUT, PATCH | `/api/administracion/detallesreporte/{id}` |
| DELETE | `/api/administracion/detallesreporte/{id}` |
| GET | `/api/administracion/conciliaciones` |
| GET | `/api/administracion/conciliaciones/{id}` |
| POST | `/api/administracion/conciliaciones` |
| PUT, PATCH | `/api/administracion/conciliaciones/{id}` |
| DELETE | `/api/administracion/conciliaciones/{id}` |
| GET | `/api/administracion/liquidaciones` |
| GET | `/api/administracion/liquidaciones/{id}` |
| POST | `/api/administracion/liquidaciones` |
| PUT, PATCH | `/api/administracion/liquidaciones/{id}` |
| DELETE | `/api/administracion/liquidaciones/{id}` |
| GET | `/api/administracion/depositosliquidacion` |
| GET | `/api/administracion/depositosliquidacion/{id}` |
| POST | `/api/administracion/depositosliquidacion` |
| PUT, PATCH | `/api/administracion/depositosliquidacion/{id}` |
| DELETE | `/api/administracion/depositosliquidacion/{id}` |
| GET | `/api/administracion/centroscosto` |
| GET | `/api/administracion/centroscosto/{id}` |
| POST | `/api/administracion/centroscosto` |
| PUT, PATCH | `/api/administracion/centroscosto/{id}` |
| DELETE | `/api/administracion/centroscosto/{id}` |
| GET | `/api/administracion/gastos` |
| GET | `/api/administracion/gastos/{id}` |
| POST | `/api/administracion/gastos` |
| PUT, PATCH | `/api/administracion/gastos/{id}` |
| DELETE | `/api/administracion/gastos/{id}` |
| GET | `/api/administracion/obligacioneslaborales` |
| GET | `/api/administracion/obligacioneslaborales/{id}` |
| POST | `/api/administracion/obligacioneslaborales` |
| PUT, PATCH | `/api/administracion/obligacioneslaborales/{id}` |
| DELETE | `/api/administracion/obligacioneslaborales/{id}` |
| GET | `/api/administracion/comisiones` |
| GET | `/api/administracion/comisiones/{id}` |
| POST | `/api/administracion/comisiones` |
| PUT, PATCH | `/api/administracion/comisiones/{id}` |
| DELETE | `/api/administracion/comisiones/{id}` |
| GET | `/api/administracion/proyectos` |
| GET | `/api/administracion/proyectos/{id}` |
| POST | `/api/administracion/proyectos` |
| PUT, PATCH | `/api/administracion/proyectos/{id}` |
| DELETE | `/api/administracion/proyectos/{id}` |
| GET | `/api/administracion/movimientosobra` |
| GET | `/api/administracion/movimientosobra/{id}` |
| POST | `/api/administracion/movimientosobra` |
| PUT, PATCH | `/api/administracion/movimientosobra/{id}` |
| DELETE | `/api/administracion/movimientosobra/{id}` |
| GET | `/api/administracion/aplicacionesobra` |
| GET | `/api/administracion/aplicacionesobra/{id}` |
| POST | `/api/administracion/aplicacionesobra` |
| PUT, PATCH | `/api/administracion/aplicacionesobra/{id}` |
| DELETE | `/api/administracion/aplicacionesobra/{id}` |
| GET | `/api/administracion/activos` |
| GET | `/api/administracion/activos/{id}` |
| POST | `/api/administracion/activos` |
| PUT, PATCH | `/api/administracion/activos/{id}` |
| DELETE | `/api/administracion/activos/{id}` |
| GET | `/api/administracion/eventosactivos` |
| GET | `/api/administracion/eventosactivos/{id}` |
| POST | `/api/administracion/eventosactivos` |
| PUT, PATCH | `/api/administracion/eventosactivos/{id}` |
| DELETE | `/api/administracion/eventosactivos/{id}` |
| GET | `/api/administracion/financiamientos` |
| GET | `/api/administracion/financiamientos/{id}` |
| POST | `/api/administracion/financiamientos` |
| PUT, PATCH | `/api/administracion/financiamientos/{id}` |
| DELETE | `/api/administracion/financiamientos/{id}` |
| GET | `/api/administracion/cuotas` |
| GET | `/api/administracion/cuotas/{id}` |
| POST | `/api/administracion/cuotas` |
| PUT, PATCH | `/api/administracion/cuotas/{id}` |
| DELETE | `/api/administracion/cuotas/{id}` |
| GET | `/api/administracion/incidencias` |
| GET | `/api/administracion/incidencias/{id}` |
| POST | `/api/administracion/incidencias` |
| PUT, PATCH | `/api/administracion/incidencias/{id}` |
| DELETE | `/api/administracion/incidencias/{id}` |
| GET | `/api/administracion/gestionesincidencia` |
| GET | `/api/administracion/gestionesincidencia/{id}` |
| POST | `/api/administracion/gestionesincidencia` |
| PUT, PATCH | `/api/administracion/gestionesincidencia/{id}` |
| DELETE | `/api/administracion/gestionesincidencia/{id}` |
| GET | `/api/administracion/aplicaciones` |
| GET | `/api/administracion/aplicaciones/{id}` |
| POST | `/api/administracion/aplicaciones` |
| PUT, PATCH | `/api/administracion/aplicaciones/{id}` |
| DELETE | `/api/administracion/aplicaciones/{id}` |
| GET | `/api/administracion/cierres` |
| GET | `/api/administracion/cierres/{id}` |
| POST | `/api/administracion/cierres` |
| GET | `/api/administracion/compensacioneslaborales` |
| GET | `/api/administracion/compensacioneslaborales/{id}` |
| POST | `/api/administracion/compensacioneslaborales` |
| PUT, PATCH | `/api/administracion/compensacioneslaborales/{id}` |
| DELETE | `/api/administracion/compensacioneslaborales/{id}` |
| GET | `/api/administracion/historial` |
| GET | `/api/administracion/historial/{id}` |
| GET | `/api/administracion/arqueosmadre/{id}/resumen` |
| POST | `/api/administracion/arqueosmadre/{id}/recalcular` |
| POST | `/api/administracion/arqueosmadre/{id}/cerrar` |
| POST | `/api/administracion/reportesmatriz/{id}/confirmar` |
| POST | `/api/administracion/conciliaciones/{id}/revisar` |
| GET | `/api/administracion/liquidaciones/{id}/resumen` |
| POST | `/api/administracion/liquidaciones/{id}/cerrar` |
| POST | `/api/administracion/depositosliquidacion/{id}/acreditar` |
| GET | `/api/administracion/cuentas/{id}/saldo` |
| GET | `/api/administracion/activos/{id}/inventario` |
| GET | `/api/administracion/proyectos/{id}/resumen` |
| GET | `/api/administracion/financiamientos/{id}/resumen` |
| GET | `/api/administracion/incidencias/{id}/resumen` |

## Apéndice F. Fuentes y verificación de esta versión

Fuentes del contrato: [rutas de caja](../routes/api.php), [rutas administrativas](../routes/administracion.php), [validaciones y recursos](../app/Support/Administracion/Recursos.php), [controlador administrativo](../app/Http/Controllers/Api/AdministracionController.php) y [servicios](../app/Services/Administracion). Los análisis de Excel son antecedentes funcionales; los endpoints efectivos y límites se tomaron del código actual.

La verificación del backend utiliza [pruebas de esquema](../tests/Schema/caja_migrations.php) y [pruebas de cuentas personales, que incluyen las regresiones anteriores](../tests/Schema/cuentas_personales_api.php). No carga .env ni utiliza una base real. El DDL MySQL se compila con conexión bloqueada; no equivale a ejecutar la aplicación en MySQL.

Verificación realizada para esta entrega:

- **624 comprobaciones de caja, 611 administrativas y 254 de cuentas personales**, aprobadas contra SQLite en memoria con rutas/controladores reales y datos ficticios.
- **444 comprobaciones de esquema** y **332 sentencias MySQL compiladas** sin conexión a un servidor MySQL.
- Los **tres bloques C#** se compilaron juntos con SDK .NET 9 y lenguaje C# 10, con advertencias tratadas como errores. El bloque breve del apéndice B se compiló dentro de su contexto de método asíncrono; no se ejecutó contra un servidor.
- El cliente administrativo y sus ejemplos superaron **51 comprobaciones con HTTP simulado**, incluyendo envolturas, campos monetarios, saldo con precisión superior a decimal, paginación, token compartido, consultas, errores, DELETE con motivo y ausencia de reintentos automáticos.
- Se verificaron la sintaxis de los bloques JSON, los enlaces locales/internos y la correspondencia del catálogo e inventario de rutas con el código actual.

Estas comprobaciones no conectan el escritorio al servidor, no validan sus formularios existentes ni ejecutan migraciones en una base real. El framework concreto del cliente y el despliegue del backend se prueban en sus entornos correspondientes.

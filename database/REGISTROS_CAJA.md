# Registros diarios de caja

Esta etapa define las migraciones, modelos y controladores de los registros base de cada cajero. Incluye apertura, captura, conteo, resumen, cierre y continuidad entre jornadas. El contrato individual se describe en [API_CAJA.md](API_CAJA.md). La ampliacion del 5 de noviembre completa `arqueo_madre` y `detalle_arqueo_madre`, y agrega consolidacion, conciliacion con matriz, cuentas y registros administrativos; su contrato esta en [API_ADMINISTRACION.md](API_ADMINISTRACION.md). La importacion de archivos sigue pendiente.

## Proteccion de lo existente

No se modifican las migraciones originales de `clientes`, `users`, `trabajadores`, `subagencias`, `mesas_cambio`, autenticacion o infraestructura. Tampoco se modifican las dos migraciones existentes que agregan `tipo_documento` a clientes e `id_subagencia` a mesas. Las relaciones nuevas se crean en las tablas de la expansion, sin agregar columnas a las tablas protegidas.

Se modifican las definiciones originales de las tablas de expansion, segun lo solicitado. Si alguna ya fue aplicada en otra instalacion, editar su archivo no actualizara esa tabla: sera necesario preparar una migracion incremental sobre el esquema real antes de desplegar. No ejecutar `migrate:fresh`, `refresh` ni un rollback general sobre una base con datos. Revisar tambien las migraciones protegidas que ya estaban pendientes antes de ejecutar cualquier lote.

## Correspondencia con los Excel

| Fuente | Registro base |
| --- | --- |
| Evelyn, saldo inicial C$/USD | `arqueo.caja_inicial_cordobas` y `caja_inicial_dolares` |
| Macro AirPak1, continuidad entre dias | `arqueo.id_arqueo_anterior`; composicion en `cheques_inicial_*` y `arqueo_cheques` |
| Evelyn, Biller E2:E3, envios E4:E5, pagos H4:H5 | Totales declarados por moneda en `arqueo` |
| Evelyn, conteo C$/USD | Una fila de `detalle_arqueo` por moneda; las cantidades son piezas |
| Evelyn, cheques y ganancias | Operacion en `cheques`; principal contado en `arqueo_cheques` |
| Evelyn, precios 1 a 4 de compra y venta | `tasas_cambio_arqueo` |
| Evelyn, cada fila de compra o venta | `operaciones_cambio_caja`, con tasa e importes aplicados |
| Mesas existentes relacionadas con caja | `operaciones_cambio_caja.id_mesa_cambio`, opcional y unico |
| Evelyn, entradas y salidas bancarias | `entradas_y_salidas`, categoria `bancario` |
| Evelyn, pases entre cajas | `pases`, con contraparte y enlace opcional al otro registro |
| Evelyn, movimientos entre agencias | `entradas_y_salidas`, categoria `entre_agencias` |
| Evelyn, gastos y movimientos varios | `entradas_y_salidas`, categorias `gasto` y `varios` |
| Evelyn, remesas Lafise/Banpro I58:M85 | `remesas_bancarias` |
| REGISTRO DHL y hoja DHL de ENROLAMIENTOS | `envios_dhl`; desglose opcional en `detalle_envios_dhl` |
| ENROLAMIENTOS, lista de personas A:E | `enrolamientos` |
| ENROLAMIENTOS, cantidades diarias G:X | `servicios_caja` y `conteos_servicios_diarios` |
| ENROLAMIENTOS, hoja PAPELERIA | `conteos_papeleria`, sin asumir inventario, consumo ni fecha |

`id_usuario` de la jornada identifica al usuario del sistema y permite consultar sus registros por fecha. `id_subagencia` conserva la agencia de esa jornada, aunque el trabajador sea trasladado posteriormente. `id_caja` admite NULL para historicos donde no se conoce el numero de caja. La regla actual es un arqueo por usuario y fecha, como el libro diario por trabajador. Si se incorporan varios turnos para el mismo cajero, esta unicidad necesitara una ampliacion explicita.

## Apertura, captura y cierre

La macro lleva el total contado anterior a la apertura: C17 a B2 y G17 a B3. C17 incluye el principal de los cheques. Por eso `caja_inicial_*` representa el total trasladado, y `cheques_inicial_*` su componente de cheques cuando se conoce. El efectivo inicial se obtiene restando ese componente. NULL en la composicion significa desconocido, no cero. Las referencias a cheques en apertura y cierre conservan la identidad del mismo documento sin volver a registrar su ganancia.

`ArqueoService` copia la apertura desde el ultimo cierre del mismo cajero y agencia, valida las fechas y conserva caja y tasa de referencia si no se proporciona otra configuracion valida. Una jornada anterior solo puede ser predecesora de una nueva jornada. Los cheques del cierre anterior se copian como referencias de apertura; su operacion y ganancia no se duplican. El conteo de cierre de la nueva jornada debe confirmarse de nuevo.

La primera apertura requiere saldos y motivo. Una apertura manual posterior requiere un administrador y un motivo. La apertura trasladada es inmutable. El arqueo debe estar abierto para modificar capturas; no se permite cambiar su propietario, fecha, agencia o caja mediante una actualizacion generica.

Los seis totales de plataforma son datos declarados. NULL significa pendiente; un cero explicito significa total informado sin importe. Se permiten los valores negativos presentes en Biller, sin fijar todavia una interpretacion de su signo. Los envios de plataforma son independientes de los envios DHL.

`detalle_arqueo.total` contiene solo efectivo. El total final por moneda incluye el efectivo y los cheques que correspondan al cierre. Los campos finales, diferencias, cantidad de cheques y ganancias agregadas son resultados derivados y comienzan en NULL. Los campos existentes `monto_total_cheques` y `ganancia_total_cheques` son resumenes en NIO; sus columnas `_dolares` corresponden a USD. La cantidad de cheques corresponde a los documentos contados al cierre. Las ganancias corresponden a las operaciones de la jornada, excluyendo el simple arrastre de custodia.

Los importes monetarios capturados usan `DECIMAL(18,2)` y las tasas `DECIMAL(12,6)`. El importe en cordobas de una operacion de cambio y las diferencias usan `DECIMAL(24,8)`: el Excel contiene productos como `5.83 * 36.5 = 212.795`, y conservar los datos base requiere evitar un redondeo implicito a centavos. Ocho decimales admiten el producto de una cantidad con dos decimales y una tasa con seis. La politica de redondeo para pagos y reportes sigue pendiente.

Se conserva el monto aplicado o calculado en origen de cada compra/venta para no recalcular historia a partir de un precio cambiado. La configuracion de tasas permite guardar precios sin operaciones; cada tasa relacionada con una operacion debe pertenecer a la misma jornada y tipo. El numero de precio debe validarse entre 1 y 4 en la aplicacion.

Los conteos pendientes tienen estado `borrador`. Las denominaciones deben validarse segun moneda: en el Excel NIO usa 1000, 500, 200, 100, 50, 20, 10, 5 y 1; USD usa 100, 50, 20, 10, 5, 2 y 1. La estructura actual conserva las columnas existentes. No incluye fracciones monetarias que no se hayan confirmado.

El servidor calcula el total del conteo con aritmetica decimal exacta y no acepta totales derivados proporcionados por el cliente. Para cerrar se requieren los seis totales diarios, los dos conteos en estado `contado` y confirmacion explicita de la custodia de cheques, incluso cuando esta vacia. Si se declaro la composicion de cheques de apertura, debe coincidir con sus documentos. El cierre guarda contado, esperado y diferencia por moneda, autor y fecha. Se permiten diferencias; no se compensan entre monedas ni se ocultan.

## Movimientos y correspondencias

Los tipos `Entrada` y `Salida` se interpretan desde la caja que declara el registro. Los pases conservan el detalle original, la caja contraparte si se conoce y un enlace opcional a la declaracion de la otra caja. Los movimientos entre agencias usan el mismo criterio. Relacionar las dos declaraciones no debe sumarlas dos veces como operaciones nuevas.

`medio` permanece NULL si la fuente no identifica como se entrego el valor. Cuando corresponda se usa `efectivo`, `cheque`, `transferencia` o `mixto`. Los movimientos varios que mezclan efectivo y cheques conservan sus componentes conocidos en `monto_efectivo` y `monto_cheques`; el servidor valida que su suma corresponda al importe declarado.

El resumen reproduce los signos de las formulas de Evelyn para valores fisicos: apertura + Biller + envios - pagos, movimientos de entrada/salida, las dos patas de cada cambio y ganancias de cheques de la jornada. El medio NULL conserva el comportamiento del libro. Cuando se identifica una transferencia entre cuentas, ese importe no se suma al efectivo ni a cheques fisicos. Un retiro bancario que entra efectivamente a caja debe declararse como efectivo. No se generan movimientos monetarios por registrar DHL, enrolamientos o cantidades de servicios.

Las remesas por banco se registran una vez, con banco, numero, moneda, direccion y monto. Los reportes obtendran cantidades y sumas agrupadas desde esas filas. No repetir el mismo registro como entrada/salida generica ni como envio de plataforma. Los numeros de remesa, guia y documento son texto para conservar ceros y formatos.

## DHL, enrolamientos y cantidades por servicio

Ambos registros DHL corresponden a enviados, segun la aclaracion del usuario. El formato abreviado no identifica necesariamente el rol de la persona: `nombre_contacto` conserva ese nombre hasta verificar su correspondencia. Remitente, beneficiario, telefono, descripcion, destino, estado y entrega son opcionales segun los datos disponibles. Se conservan fecha y confirmacion originales cuando requieren revision. Las guias estan indexadas sin unicidad global para no descartar repeticiones antes de verificarlas. No se crean ingresos ni tarifas a partir de estos registros, porque los Excel no incluyen esos importes. Despues de cerrar la caja se permite actualizar solamente seguimiento, confirmacion, entrega y observacion del envio, con historial; guia, contenido y relaciones quedan bloqueados.

Enrolamientos guarda un evento y una fotografia del nombre/documento, con enlace opcional al cliente existente. No actualiza ni da de alta clientes. El codigo `USUARIO` del Excel se conserva en `usuario_servicio`: no se utiliza como clave primaria de `users`. Una persona puede tener varios eventos. Las fechas faltantes o invalidas pueden conservarse con fecha normalizada NULL y texto original, en estado `borrador`.

Los conteos por servicio son cantidades declaradas independientes de la lista de personas. No asociar ambos bloques por numero de fila ni deducir cobros a partir de cantidades. La ausencia de una fila de conteo significa no informado; una fila con cantidad cero es un valor declarado. El catalogo de servicios se poblara explicitamente, incluyendo los conceptos presentes en los libros (FDL, FAMA, FICOHSA, FUNDESER, CR envios/pagos, VIGO, DHL, seguros, PIC, LAFISE, MI CREDIT, M/C y remesas), verificando alias antes de unificarlos.

PAPELERIA conserva concepto y cantidad por trabajador. Fecha, agencia, servicio y arqueo son opcionales porque la hoja no aclara su periodo ni significado. No asignar automaticamente esos conteos al dia de importacion.

## Segunda revision e integracion

Los modelos y controladores ya corresponden al esquema. Las rutas anteriores de la expansion conservan sus nombres y los metodos de consulta agregados previamente. Se agregan recursos para las tablas nuevas, resumen/cierre, extraccion diaria e historial. Se corrigen las rutas del ultimo precio y de consulta de una mesa por ID, sin modificar su tabla ni controlador.

La segunda revision agrega la moneda de la ganancia del cheque, vinculo compuesto para impedir una custodia en otra moneda, saldos esperados de cierre, confirmacion de cheques e `historial_caja`. Las nuevas API validan propietarios, importes, denominaciones, precios 1 a 4, tasas, componentes mixtos y contrapartes. Los cajeros acceden a sus jornadas y registros; un usuario con el rol existente `Administrador` puede supervisarlos. Cajas, servicios y precios de proveedor requieren ese rol para sus cambios. El permiso para conciliar declaraciones de cajeros distintos tambien corresponde al administrador.

Las escrituras, calculos e historial se guardan en una transaccion. Los registros financieros se anulan con motivo; los conteos, tasas y desgloses pueden eliminarse mientras su jornada este abierta y sus dependencias lo permitan, conservando su fotografia en el historial. No se eliminan registros relacionados en cascada. Una jornada cerrada no admite cambios financieros ni reapertura generica. Se permite seguimiento posterior de DHL y liquidacion de cheques sin anular/reactivar operaciones anteriores.

Los modelos y controladores existentes de autenticacion, administracion de usuarios, clientes, trabajadores, agencias y mesas no se modifican. Las rutas de escritura de usuarios ahora requieren administrador para impedir que un cajero cree un administrador o cambie roles y eluda la autorizacion de caja. Esta revision no es una auditoria integral de los permisos restantes de los modulos existentes. La proteccion por propietario descrita aqui corresponde a los recursos nuevos y a los controladores de caja adaptados.

Siguen pendientes la politica de redondeo para pagos, importacion automatica, validacion supervisada de duplicados historicos, reversiones/reaperturas supervisadas y los reportes posteriores. La historia guarda autor, fecha y valores antes/despues de cada escritura en las nuevas API; no reconstruye horas de Excel que no se hayan importado.

Las migraciones de medicamentos y facturas existentes quedan fuera de este ajuste: los tres Excel revisados no definen su captura. No deducir que el valor de una factura declarativa sea un ingreso de caja.

## Verificacion sin base real

Desde la raiz del proyecto:

```powershell
php tests/Schema/caja_migrations.php
php tests/Schema/caja_api.php
```

La prueba de migraciones usa SQLite en memoria, crea registros ficticios, comprueba las relaciones y revierte la expansion conservando tablas y filas protegidas. Tambien compila el DDL para MySQL con una conexion PDO que lanza una excepcion si se intenta utilizar: no se conecta a un servidor MySQL.

La prueba de API ejecuta las rutas y controladores reales contra otra base SQLite en memoria, con usuarios ficticios en lugar de tokens reales. Comprueba autorizacion, validacion, aritmetica, cierre, arrastre, seguimiento e historial. Reproduce los registros de la hoja 08 y verifica esperado NIO 148105.41 / USD 1611.64 y diferencias NIO 32.59 / USD -0.64. Ninguna prueba lee `.env`, ejecuta Artisan o usa una base real. No sustituyen una prueba posterior con MySQL aislado ni la validacion del inicio de sesion mediante Sanctum.

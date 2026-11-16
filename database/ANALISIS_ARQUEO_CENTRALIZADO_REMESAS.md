# Arqueo Centralizado de Remesas: análisis e implementación

Fecha de revisión: 2026-11-05. Fuente: `C:\Users\jerlg\Downloads\Arqueo Centralizado de Remesas.xlsm`.

Segunda revisión del 2026-11-06: [REVISION_SERVICIOS_EXTERNOS.md](REVISION_SERVICIOS_EXTERNOS.md). Incluye fallos corregidos y la confirmación comercial posterior del tramo único y de los movimientos sin comisión.

SHA-256: `745f38a132a743ae1fc914436a3992021745bb05a038b8f82406f4d4e2391ed0`.

Se inspeccionaron el contenido OOXML, las fórmulas, sus resultados guardados y el código VBA. No se ejecutaron macros, actualizaron vínculos ni modificó el archivo. Las instrucciones o textos del libro se trataron como contenido de referencia. Las reglas de ganancias provienen de las instrucciones del usuario.

## 1. Qué registra realmente el archivo

Tiene 51 hojas visibles: 24 fechas de septiembre, 26 de octubre y `00`. Los nombres históricos no indican año, por lo que no permiten asignar uno a los datos. `00` contiene 32 filas registradas; no es una plantilla vacía.

Cada día tiene tres bloques:

| Ubicación en `00` | Función | Dato / cálculo |
| --- | --- | --- |
| `A3:G193` | Encabezados y captura individual | Agencia, cajero, banco, tipo, moneda, número de remesa y monto. |
| `I4:M12` | Totales por banco, dirección y moneda | Ocho combinaciones: LAFISE/BANPRO × Entrada/Salida × C$/USD. `COUNTIFS` cuenta filas y `SUMIFS` suma principal. |
| `I17:U45` | Totales por agencia y banco | 14 agencias × dos bancos; cuatro cantidades y cuatro importes de entrada/salida por moneda. |
| `S18:S45` | Cantidad por agencia/banco | Suma las cuatro cantidades, no el dinero. |
| `T18:U45` | Neto por moneda | Entrada menos salida; no representa ganancia. |
| `K47:U47` | Total visible | `SUBTOTAL(109,...)`: los filtros cambian la vista totalizada. |

Las 14 agencias del bloque son Siuna III, Siuna II, Río Blanco III, La Dalia, Bonanza VI, Bonanza VII, Mulukukú IV, Santa Rita, San Pedro, Coperna, Matiguás, El Tuma, Río Blanco IV y Mulukukú III. Es una lista fija del archivo, no un catálogo que deba importarse a producción automáticamente.

En `00`, el total guardado es 32 transacciones: 4 entradas NIO por C$11,600, 2 salidas NIO por C$3,000, 25 entradas USD por USD8,609.96 y una salida USD por USD700. El neto es C$8,600 y USD7,909.96. Ninguno de esos valores es una comisión.

Las 51 hojas contienen 1,914 filas de captura y 17,085 celdas de fórmula. Se verificaron los conteos y sumas principales por banco y agencia mediante cálculos independientes, sin discrepancias con los resultados guardados a una tolerancia de 0.000001. Esto verifica los datos y cálculos inspeccionados, no una ejecución nativa de Excel.

No existen partes `externalLinks` en este archivo. Tampoco se encontraron fórmulas de ganancias ni una columna que clasifique transacciones de cuentas, servicios públicos, empresas o remesas locales/extranjeras. Ese detalle debe añadirse para aplicar las tarifas indicadas.

## 2. Macro de cambio de día

El procedimiento `Remesas` del módulo `Módulo1`, asociado a Ctrl+G, realiza este flujo:

1. Copia la hoja `00` después de la primera hoja.
2. Renombra la copia como `cambiar`.
3. Limpia `00!A4:G193`.
4. Regresa a `00!B2` y modifica la posición de la vista.

No asigna una fecha al archivo histórico ni calcula ganancias. El nombre `cambiar` debe sustituirse manualmente; si ya existe, el renombrado puede fallar. La limpieza y el resumen superior abarcan hasta la fila 193, mientras el resumen por agencias consulta hasta la 1000. Ningún registro de este ejemplar supera la fila 193, pero al ampliarlo podría quedar información fuera del resumen superior o sin limpiar.

La API sustituye la copia y limpieza por registros fechados independientes, con cierre e historial. No requiere ejecutar esta macro.

## 3. Calidad y significado de los datos

- Hay 34 combinaciones día/banco/referencia repetidas. No prueban duplicidad: la columna F también contiene números de cuenta y textos operativos. No se impuso unicidad sobre esa referencia.
- Hay 12 filas con campos incompletos; 11 de ellas no tienen referencia. No se completaron ni importaron automáticamente.
- Existen referencias numéricas, alfanuméricas, con espacios y saltos de línea. En el sistema se conservan como texto; C# no debe convertirlas a números ni perder ceros iniciales.
- Los importes pagados en NIO no contienen por sí solos el importe original USD requerido por la nueva tarifa de remesas extranjeras BANPRO. No se deduce ese importe con una tasa supuesta.
- Los totales del archivo incluyen el principal de todos los servicios. No permiten reconstruir ganancias históricas exactas sin clasificar cada fila y completar las bases USD faltantes.

## 4. Alcance confirmado y criterio tarifario implementado

Existe una sola terminal o punto de servicio. Una persona realiza la captura administrativa; no se exige a cada cajero llenar este nuevo módulo. Las agencias y cajeros del detalle identifican a qué operación corresponde la fila, no crean terminales ni registros diarios separados por empleado.

El rango BANPRO utiliza el total diario de transacciones de cuentas de esa terminal, sin dividir por agencia, cajero, moneda o dirección. Se aplica una tarifa única a todas las transacciones de cuentas del día: 11 × C$5.50 = C$60.50; 31 × C$7 = C$217. Las otras clases de servicio no aumentan ese contador. No se implementó un esquema progresivo por bloques.

En la respuesta posterior de la segunda revisión, el usuario confirmó expresamente C$60.50 para once transacciones: una tarifa para todas. También confirmó que existen movimientos sin comisión. Estos se registran como `sin_comision`, con motivo obligatorio y ganancia cero, y no modifican el contador de cuentas BANPRO. Las filas todavía `sin_clasificar` siguen pendientes y no se convierten a cero.

El identificador de terminal es `principal`, asignado por el servidor. La API no ofrece crear más terminales en esta entrega. Una futura ampliación requerirá habilitar ese catálogo y definir sus permisos, sin multiplicar ahora la captura de los cajeros.

## 5. Tarifas incorporadas

| Proveedor / servicio | Ganancia NIO por fila |
| --- | --- |
| LAFISE: transacción de cuenta | 7 |
| LAFISE: remesa | 20 |
| BANPRO: cuenta, 1–10 transacciones diarias | 5 |
| BANPRO: cuenta, 11–20 | 5.50 |
| BANPRO: cuenta, 21–30 | 6 |
| BANPRO: cuenta, 31 o más | 7 |
| BANPRO: recepción de empresas e instituciones | 5 |
| BANPRO: servicios públicos | 4 |
| BANPRO: remesa local | 12 |
| BANPRO: remesa extranjera pagada en USD | 13 |
| BANPRO: extranjera pagada NIO, base USD0.01–29.99 | 13 |
| BANPRO: extranjera pagada NIO, base USD30–100.99 | 24 |
| BANPRO: extranjera pagada NIO, base USD101–200.99 | 28 |
| BANPRO: extranjera pagada NIO, base USD201–400.99 | 33 |
| BANPRO: extranjera pagada NIO, base USD401–1500 | 38 |
| Ambos bancos: movimiento confirmado sin comisión | 0, con motivo obligatorio |

El nombre canónico de API es `banpro`, aunque el mensaje del usuario escribe BAMPRO. Todas las ganancias son NIO, incluso si el principal está en USD. Los límites USD tienen dos decimales; los valores fuera del rango autorizado para pago NIO se rechazan. La tarifa fija de pago USD no tiene un límite de principal indicado por el usuario.

## 6. Comparación con lo que ya existía

| Función previa | Faltante | Integración |
| --- | --- | --- |
| `remesas_bancarias` por caja | Registro independiente de la terminal administrativa y clase de servicio | Nueva captura administrativa; enlace opcional de contraste a una remesa de caja cerrada. |
| `reportes_remesa_central` de Libro Diario | Sus cifras se capturaban directamente, sin detalle transaccional de este libro | El cierre externo genera las ocho líneas por agencia, incluidos ceros, y conserva `id_arqueo_externo`. |
| `comisiones_administrativas` | Se capturaba el monto de comisión sin este cálculo diario | Acción de reconocimiento crea una comisión por proveedor a partir de la ganancia congelada. |
| Cuenta por cobrar y aplicaciones | Ya permitían cargo, abonos y cobros | Se reutilizan; no se abre otra contabilidad de comisiones. |
| Capital y cierre financiero | No conocían este nuevo origen | Agregan fuentes y pendientes de cierre o reconocimiento, sin sumar principal ni ganancias dos veces. |
| Historial financiero | Ya registraba cambios administrativos | Conserva captura, anulación, cálculo definitivo, emisión de reporte y reconocimiento. |

La captura usa perspectiva central/POS, igual que el reporte ya documentado en Libro Diario. Una referencia opcional a caja exige dirección opuesta, misma fecha, agencia, banco, moneda, importe y número de remesa. No se invierten valores silenciosamente ni se importan las cajas como si fueran un reporte independiente.

## 7. Cambios físicos y límites

La migración `2026_11_05_000005_servicios_externos.php` crea dos tablas: `arqueos_servicios_externos` y `transacciones_servicios_externos`. Añade procedencia nullable al reporte central y a las comisiones existentes. Las migraciones y tablas maestras protegidas no se modificaron.

La confirmación de movimientos sin comisión añade `motivo_sin_comision` nullable al detalle en esa misma migración no protegida, sin ejecutar cambios sobre bases reales. La API lo exige para esa clasificación. Los contadores distinguen movimientos con y sin comisión; todos sus principales siguen formando parte del reporte. El tarifario `2026-11-06.v2` conserva las tarifas remuneradas y agrega cero a esa clase en ambos bancos.

Se añadieron dos modelos, un servicio, un catálogo de validaciones, un controlador y 18 combinaciones método/ruta bajo administración. El registro activo es único por día y terminal. Los borradores pueden corregirse o anularse; un cierre conserva el resumen y sus tarifas. El tarifario se copia al crear cada jornada, para que un cambio futuro no recalcule su historia.

No se crea movimiento de efectivo al capturar, cerrar o reconocer una comisión. El cobro posterior utiliza una operación financiera y una aplicación existentes. Un cargo de comisión sin pagos puede anularse y reemitirse conservando su historia; una comisión aplicada requiere resolver antes sus dependencias y respetar cierres financieros.

No se importaron datos históricos ni se ejecutaron migraciones en una base real. Esta entrega corresponde al backend y su contrato para la futura pantalla de C#.

Contrato de consumo y flujo de pantalla: [API_SERVICIOS_EXTERNOS.md](API_SERVICIOS_EXTERNOS.md).

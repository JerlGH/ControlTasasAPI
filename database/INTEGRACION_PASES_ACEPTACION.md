# Pases de efectivo con aceptación

Contrato del 23 de noviembre de 2026 para el cliente de arqueo de trabajadores.

Aplicar la migración `2026_11_23_000000_solicitudes_pases.php` antes de activar el código. Agrega solicitudes, UUID único, participantes, jornadas, referencias a salida/entrada, estado, motivo y fecha de resolución. No traslada ni reescribe los movimientos históricos.

## Rutas autenticadas

| Método y ruta | Uso |
|---|---|
| GET solicitudespases/destinatarios?id_arqueo=ID&entre_subagencias=0 | Cajeros activos de la misma subagencia, excluyendo al remitente |
| GET solicitudespases/destinatarios?id_arqueo=ID&entre_subagencias=1 | Cajeros activos de todas las subagencias |
| GET solicitudespases | Solicitudes pendientes recibidas por el usuario autenticado |
| GET solicitudespases?id_arqueo=ID | Solicitudes del usuario vinculadas a su jornada |
| GET solicitudespases?clave=UUID | Verificación de un alta cuya respuesta se perdió |
| GET solicitudespases/ID | Consulta de una solicitud visible para alguno de sus participantes |
| POST solicitudespases | Registrar salida y solicitud pendiente |
| POST solicitudespases/ID/aceptar | Crear entrada y vincular ambos pases |
| POST solicitudespases/ID/rechazar | Anular salida sin crear entrada; requiere motivo |

Alta:

```json
{
  "clave": "a9f03256-a962-4a82-9cee-1e3cb0c8e744",
  "id_arqueo": 123,
  "id_usuario_destino": 8,
  "entre_subagencias": false,
  "moneda": "NIO",
  "monto": "999999.99",
  "referencia": "0000123",
  "observacion": "Efectivo entregado"
}
```

La respuesta de escritura contiene `Solicitud` y la lista contiene `Solicitudes`. Los destinos contienen `id_usuario`, `id_subagencia`, `nombre`, `ubicacion` y `texto` con formato Ubicación - Nombre cajero. La lista de solicitudes incluye nombre y ubicación del remitente, sin exponer sus credenciales.

## Garantías

- El remitente solo puede usar su propia jornada abierta. El destinatario debe ser un usuario activo con rol Caja o Cajero, trabajador y subagencia activos y una jornada abierta.
- Cruzar subagencias exige habilitarlo expresamente. La jornada destino queda fijada al solicitar.
- Crear registra la salida de efectivo inmediatamente. Aceptar crea una entrada idéntica y enlaza ambos movimientos dentro de una transacción. Rechazar anula la salida.
- Se bloquean ambas jornadas en orden estable antes de escribir, coordinándose con su cierre. Un fallo de persistencia revierte aceptación y vínculos.
- Solo el destinatario acepta o rechaza. El mismo UUID y cuerpo devuelven la solicitud existente; otro cuerpo con esa clave devuelve 409. Repetir la misma decisión no crea movimientos; una decisión contradictoria devuelve 409.
- Los movimientos de una solicitud no pueden editarse, anularse o enlazarse por el CRUD heredado.
- Las solicitudes pendientes impiden cerrar ambas jornadas. Los pases pendientes o aceptados impiden anular una sola jornada.
- La modificación de tasas de arqueo ahora exige administrador; su consulta conserva el alcance de lectura existente.
- Las solicitudes y cada movimiento conservan auditoría. No se ejecutan correcciones históricas automáticas.

## Comprobaciones

```powershell
php tests/Schema/solicitudes_pases_api.php
php tests/Schema/caja_migrations.php
```

El primer comando ejecuta también las 683 verificaciones previas de caja y agrega 99 comprobaciones del flujo. El segundo verifica 549 condiciones de migración y reversión con SQLite en memoria, y compila SQL MySQL sin conectarse a ese motor.

Las pruebas no cargan .env ni conectan una base real. La concurrencia se protege con transacciones y bloqueos de jornadas; estas pruebas en SQLite no equivalen a una prueba de carga concurrente en el MySQL de despliegue.

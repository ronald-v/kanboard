# Tiempo, facturable y bolsas

## Glosario
- Trabajado: duración registrada; no implica facturación.
- Facturable: clasificación comercial autorizada de trabajo.
- Consumo: movimiento que descuenta una bolsa bajo una regla aprobada.
- Facturado: trabajo incluido en una factura; emisión de facturas fuera del alcance.
- Estimación: previsión de esfuerzo, no trabajo ni consumo.
- Saldo: créditos/ampliaciones/ajustes menos consumos según ledger, no un contador aislado.

## Verificado en la base
Tareas/subtareas permiten horas manuales. Sesiones por usuario/subtarea con inicio y
fin persistidos; no dependen del navegador. getHours calcula round(segundos/3600,2).
Cambiar subtareas recalcula totales de tarea; nunca sumar ambos niveles.
El modelo permite timers del mismo usuario en subtareas distintas (también en tests).
Inicio usa comprobación + inserción; stop actualiza acumulados antes del cierre sin
transacción explícita en ese método. No hay garantía de concurrencia comprobada.
updateSubtaskTimeSpent pasa false para eventos; no basar un ledger solo en listeners.
Fuentes: app/Model/SubtaskTimeTrackingModel.php, app/Model/SubtaskModel.php,
app/Core/DateParser.php, tests/units/Model/SubtaskTimeTrackingModelTest.php.

## Fuente de verdad propuesta
Reutilizar subtask_time_tracking como detalle del trabajo; duración nueva en segundos
enteros derivados de sus timestamps. Mantener horas nativas como proyección compatible
con conversión explícita. No introducir una segunda tabla de sesiones autónoma.
La extensión almacena clasificación, aprobaciones, clientes, contratos y movimientos,
referenciando origen único. El ledger es fuente del saldo, no un segundo registro de
trabajo. Acumulados históricos sin sesiones permanecen identificados como tales.

Antes de adoptar esto en F-007, demostrar escritura manual y automática por todas
las entradas UI/API, protección de registros cerrados y política de eliminación.
Si hay límites del nativo, registrar decisión arquitectónica y alternativa; no
rellenar historial ficticio para reconciliar. Ningún esquema se ha creado.

## Contrato comercial aprobado en DEC-008
El usuario aceptó la propuesta BR-01..10 con «Me gusta, vamos con esas» el
2026-11-12. Registro inmutable: [DEC-008](../feedback/2026-11-12-DEC-008-f003-contrato-comercial.md).
Las alternativas históricas no elegidas quedan fuera de esta implementación.

| ID | Decisión | Alternativas | Bloquea |
| --- | --- | --- | --- |
| BR-01 | Cliente/proyecto/bolsa | Un cliente por proyecto; bolsas por proyecto, sin compartir entre proyectos | F-009/F-010 |
| BR-02 | Ciclo de bolsa | Horas fijas ampliables; vigencia opcional; sin renovación ni arrastre automático | F-009/F-010 |
| BR-03 | Momento de consumo | Al aprobar trabajo facturable; registrar trabajo no consume por sí solo | F-010 |
| BR-04 | Agotamiento | Avisar y permitir excedente; saldo puede ser negativo | F-010 |
| BR-05 | Clasificación/corrección | Autor registra/corrige pendiente; líder o administrador clasifica y aprueba | F-007/F-008 |
| BR-06 | Aprobación y cierre | Rectificación de aprobado por líder/administrador, con auditoría | F-008/F-010 |
| BR-07 | Tarifas | Solo reportes de horas; importes y tarifas excluidos inicialmente | F-011 |
| BR-08 | Visibilidad | Miembro: propias horas; líder: horas y saldos de sus proyectos; administrador: todo | F-008/F-009/F-011 |
| BR-09 | Temporizadores | Uno por usuario; sesiones olvidadas se corrigen manualmente | F-007 |
| BR-10 | Fechas/reportes | America/Bogota; filtros por fecha trabajada; corrección auditada sin cierre mensual automático | F-007/F-011 |

No presuponer que todo trabajo es facturable. Se distinguen pendiente, aprobado y
rectificado sin borrar historia; aprobación no significa facturado. No introducir
tarifas, facturas, impuestos, pagos, portal de cliente, renovación automática ni
bolsas compartidas. Detalles comerciales no cubiertos requieren decisión expresa
en la feature que los necesite; no inferir autorización de la autonomía técnica.

## Invariantes técnicas
Operaciones atómicas, reintentos idempotentes, claves de origen únicas, auditoría de
actor/fecha/motivo/antes/después. Correcciones mediante movimiento compensatorio o
versión trazable según contrato; saldo reconciliable. Snapshot de tarifa/moneda en
reportes cerrados si se calculan importes: cambios posteriores no alteran historia.
Nueva duración no negativa, fin >= inicio, límite de fechas aprobado. Segundos y
horas explícitos; históricos no migrados automáticamente. Servidor conserva sesión.
Autorización por objeto incluso en API/exportación; bolsas compartidas no amplían
por sí solas el acceso a proyectos ni a tarifas.

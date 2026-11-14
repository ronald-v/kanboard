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

## Contrato comercial pendiente
Necesidad confirmada; alternativas sin elección aprobada:

| ID | Decisión | Alternativas | Bloquea |
| --- | --- | --- | --- |
| BR-01 | Cliente/proyecto/bolsa | Un cliente por proyecto; bolsa por proyecto o compartida | F-009/F-010 |
| BR-02 | Ciclo de bolsa | Horas fijas/ampliaciones; vigencia; vencimiento; renovación; arrastre o no | F-009/F-010 |
| BR-03 | Momento de consumo | Registrado, aprobado u otro evento acordado | F-010 |
| BR-04 | Agotamiento | Avisar, bloquear o permitir excedente | F-010 |
| BR-05 | Clasificación/corrección | Autor, líder o administrador; alcance de cambios | F-007/F-008 |
| BR-06 | Aprobación y cierre | Sin aprobación o flujo explícito; reapertura/rectificación | F-008/F-010 |
| BR-07 | Tarifas | Cliente/proyecto/persona; moneda, vigencia y redondeo | Importes de F-011 |
| BR-08 | Visibilidad | Horas propias/equipo; saldos; tarifas; reportes | F-008/F-009/F-011 |
| BR-09 | Temporizadores | Uno por usuario o simultáneos; sesiones olvidadas y corrección manual | F-007 |
| BR-10 | Fechas/reportes | America/Bogota propuesta; corte, retroactividad y límites | F-007/F-011 |

No presuponer que todo trabajo es facturable. Registrar decisión y actor en feedback
antes de calcular. Una regla bloquea su feature, no la inspección ni diseño independiente.

## Invariantes técnicas
Operaciones atómicas, reintentos idempotentes, claves de origen únicas, auditoría de
actor/fecha/motivo/antes/después. Correcciones mediante movimiento compensatorio o
versión trazable según contrato; saldo reconciliable. Snapshot de tarifa/moneda en
reportes cerrados si se calculan importes: cambios posteriores no alteran historia.
Nueva duración no negativa, fin >= inicio, límite de fechas aprobado. Segundos y
horas explícitos; históricos no migrados automáticamente. Servidor conserva sesión.
Autorización por objeto incluso en API/exportación; bolsas compartidas no amplían
por sí solas el acceso a proyectos ni a tarifas.

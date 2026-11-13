# DEC-016: sesiones únicas e integridad transaccional

Fecha: 2026-11-12T22:19:05-05:00. Feature: F-007.
Contrato aprobado: DEC-008; continuidad sin aprobaciones visuales: DEC-015.

Decisión: conservar subtask_time_tracking como único detalle de sesiones. Metadata
referenciada almacena segundos exactos, origen, nota, clave/hash de operación y
versión; auditoría inmutable registra actor, motivo, antes/después. No segunda
tabla independiente de trabajo. Horas nativas redondeadas son proyección compatible.

Mutex por usuario serializa creación/manual/cierre/corrección; mutex por tarea
protege proyecciones entre usuarios. Transacciones PDO y savepoints conservan
transacciones externas. Mismo inicio devuelve sesión activa; otra subtarea se
rechaza. Repetir cierre no suma; misma clave manual/corrección conserva resultado
y rechaza payload distinto. Restricción FK protege historia ante cascadas nativas.

Acumulados históricos se capturan opacamente en migración 2 y se conservan en
proyecciones por entidad. No repartir diferencias tarea/subtarea ni inventar
sesiones. Migración 3 añade mutex por tarea. No calcular trabajo sumando tarea y
subtareas. Solo nuevos registros con metadata entran en el detalle comercial.

Adaptación técnica necesaria: servicio nativo de seguimiento y modelos de escritura
de acumulados sustituidos vía contenedor. Horas manuales sin fecha se rechazan en
servidor; helpers muestran acumulado readonly y enlace a sesión fechada. Se conserva
capacidad manual con precisión y auditoría mediante UI/API propios, sin editar core.

Fechas: segundos no negativos, fin>=inicio, sin trabajo futuro, America/Bogota,
sin cierre mensual ni límite retroactivo artificial. Fin/duración explícitos en UI
permiten derivar inicio; no son una reconstrucción de historia ausente. Límites
técnicos de tamaño y entero, sin redondear la fuente exacta.

Siguiente acción: F-008 clasificación/alcance por rol y protección de aprobado.
Recuperación: código y DB compatibles juntos; deshabilitar plugin no elimina
metadata, auditoría ni snapshots. SQLite probado; MySQL requiere ejecución antes
de release a ese motor. Postgres/otros no se declaran soportados por el plugin.
